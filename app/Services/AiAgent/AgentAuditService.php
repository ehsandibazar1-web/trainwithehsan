<?php

namespace App\Services\AiAgent;

use App\Filament\Pages\MediaLibrary;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\AiAuditRun;
use App\Models\AiRecommendation;
use App\Models\Article;
use App\Models\Keyword;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\InternalLinking\LinkGraphService;
use App\Services\Seo\HtmlContentScanner;
use App\Services\Seo\SeoAuditService;
use Illuminate\Support\Collection;

/**
 * موتور تشخیصِ AI Agent — «پروآکتیو» یعنی همین: به‌جای منتظر ماندن برای درخواست ادمین، همه‌ی
 * محتوای سایت را می‌گردد و فرصت‌های بهبود را پیدا می‌کند. طبق دستور صریح کاربر («Do NOT rebuild
 * existing functionality. Reuse everything already implemented.») این کلاس تقریباً هیچ منطق
 * تشخیصِ تکراری ندارد — هر جا سرویس موجودی (SeoAuditService/LinkGraphService/ContentReviewService)
 * همان چک را دارد، همان صدا زده می‌شود؛ فقط برای دسته‌هایی که واقعاً جای‌دیگری وجود ندارند
 * (تازگی محتوا، مقدمه/نتیجه‌گیریِ ضعیف، محتوای کم‌عمق، تاپیک تکراری، کانیبالیزیشن، نیاز به ترجمه)
 * منطق تازه‌ی کوچک نوشته شده — هم‌روح SuggestionEngine/KnowledgeBaseService (امتیازدهیِ
 * دست‌ساز و قابل‌توضیح، بدون کتابخانه‌ی تازه).
 *
 * هر متد یک دسته را برمی‌گرداند؛ هیچ‌کدام چیزی ذخیره نمی‌کنند (run() قابل‌تست و بدون اثر جانبی
 * است) — ذخیره‌سازی/upsert فقط در persist() اتفاق می‌افتد، هم‌روح
 * SuggestionEngine::generateAndPersist() (هرگز ردیف‌های applied/rejected را دست نمی‌زند).
 */
class AgentAuditService
{
    // مقاله/صفحه‌ی منتشرشده‌ای که این‌قدر روز از آخرین ویرایشش گذشته، «نیازمند تازه‌سازی» است —
    // هم برای «Articles needing updates» و هم «Content that should be refreshed»: دو عنوان
    // درخواستیِ کاربر، یک سیگنالِ واحد (هم‌روح صداقتِ SeoAuditService::missingCanonicals())
    private const STALE_DAYS = 180;

    private const THIN_CONTENT_WORDS = 300;

    private const WEAK_INTRO_WORDS = 40;

    private const WEAK_CONCLUSION_WORDS = 25;

    private const DUPLICATE_TOPIC_JACCARD = 0.4;

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'can', 'will', 'about', 'into', 'more', 'than',
        'için', 'veya', 'çok', 'daha', 'gibi', 'ama', 'ile', 'olan', 'olarak', 'bir', 'bu', 'şu',
    ];

    public function __construct(
        private readonly SeoAuditService $seoAudit,
        private readonly LinkGraphService $linkGraph,
        private readonly ContentReviewService $reviewService,
        private readonly HtmlContentScanner $scanner,
    ) {}

    /**
     * تمام شانزده دسته — بدون تماس شبکه‌ای، مگر اینکه $includeExternalLinks صریحاً خواسته شود
     * (همان الگوی SeoAuditService::checkExternalLinks — کند و شبکه‌ای، فقط با اجرای کامل/دستی).
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function run(bool $includeExternalLinks = false): array
    {
        $items = $this->seoAudit->collectContentItems();
        $published = $items->filter(fn ($item) => $item['status'] === 'published')->values();
        // یک‌بار بارگذاری Article/Page واقعی برای همه‌ی موارد منتشرشده — هر متدی که به updated_at/
        // translation_of نیاز دارد از همین نگاشت می‌خواند، نه یک find() تازه به‌ازای هر مورد/جفت
        $records = $this->loadRecords($published);

        $seoFindings = $this->seoAudit->run();
        $nodes = $this->linkGraph->build()['nodes'];

        $brokenLinks = $seoFindings['broken_internal_links'];
        if ($includeExternalLinks) {
            $brokenLinks = array_merge($brokenLinks, $this->seoAudit->checkExternalLinks());
        }

        return [
            'content_refresh' => $this->staleContent($published, $records),
            'missing_internal_links' => $this->missingInternalLinks($nodes),
            'missing_faq' => $this->reviewBasedFindings($published, $records, 'missing_faq_opportunity', fixField: 'faq', fixMode: 'generate', modelFilter: 'Article'),
            'weak_intro' => $this->weakParagraph($published, first: true),
            'weak_conclusion' => $this->weakParagraph($published, first: false),
            'missing_cta' => $this->reviewBasedFindings($published, $records, 'weak_cta', fixField: 'cta', fixMode: 'generate'),
            'missing_alt' => $this->missingAlt($seoFindings['missing_alt']),
            'broken_links' => $this->wrapReviewOnly($brokenLinks),
            'thin_content' => $this->thinContent($published),
            'duplicate_topics' => $this->duplicateTopics($published, $records),
            'content_cannibalization' => $this->contentCannibalization(),
            'missing_schema' => $this->wrapReviewOnly($seoFindings['missing_schema']),
            'poor_seo' => $this->poorSeo($published, $records),
            'image_optimization' => $this->imageOptimization(),
            'needs_translation' => $this->needsTranslation($published, $records),
            'orphan_pages' => $this->wrapReviewOnly($seoFindings['orphan_pages']),
        ];
    }

    /**
     * اجرا + ذخیره‌سازی — یک App\Models\AiAuditRun می‌سازد، یافته‌ها را upsert می‌کند (فقط ردیف‌های
     * pending دست می‌خورند)، و pending‌های قدیمی‌ای که دیگر تکرار نشدند را پاک می‌کند. هرگز به
     * ردیف‌های applied/rejected دست نمی‌زند — تصمیم ادمین همیشه باقی می‌ماند (هم‌روح
     * SuggestionEngine::generateAndPersist و قانون‌های ثبت‌شده در CLAUDE.md).
     */
    public function generateAndPersist(string $triggerType = 'manual', bool $includeExternalLinks = false): AiAuditRun
    {
        $auditRun = AiAuditRun::create([
            'trigger_type' => $triggerType,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $categorized = $this->run($includeExternalLinks);
            $result = $this->persist($categorized, $auditRun);

            $auditRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'found_count' => $result['found'],
                'new_count' => $result['new'],
                'resolved_count' => $result['resolved'],
            ]);
        } catch (\Throwable $e) {
            $auditRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        return $auditRun->fresh();
    }

    /**
     * @return array{found: int, new: int, resolved: int}
     */
    private function persist(array $categorized, AiAuditRun $auditRun): array
    {
        $decidedKeys = AiRecommendation::whereIn('status', ['applied', 'rejected'])
            ->get(['category', 'content_type', 'content_id', 'related_content_type', 'related_content_id', 'locale'])
            ->map(fn ($row) => $this->rowKey($row->toArray()))
            ->flip();

        $existingPendingKeys = AiRecommendation::pending()
            ->get(['category', 'content_type', 'content_id', 'related_content_type', 'related_content_id', 'locale'])
            ->map(fn ($row) => $this->rowKey($row->toArray()))
            ->flip();

        $now = now();
        $rows = [];
        $freshKeys = [];
        $newCount = 0;

        foreach ($categorized as $category => $findings) {
            foreach ($findings as $finding) {
                $normalized = [
                    'category' => $category,
                    'content_type' => (string) ($finding['content_type'] ?? ''),
                    'content_id' => (int) ($finding['content_id'] ?? 0),
                    'related_content_type' => (string) ($finding['related_content_type'] ?? ''),
                    'related_content_id' => (int) ($finding['related_content_id'] ?? 0),
                    'locale' => (string) ($finding['locale'] ?? ''),
                ];

                $key = $this->rowKey($normalized);
                $freshKeys[] = $key;

                if ($decidedKeys->has($key)) {
                    continue; // ادمین قبلا تصمیم گرفته — دست نمی‌زنیم
                }

                if (! $existingPendingKeys->has($key)) {
                    $newCount++;
                }

                $rows[] = array_merge($normalized, [
                    'audit_run_id' => $auditRun->id,
                    'severity' => $finding['severity'] ?? 'notice',
                    'title' => $finding['title'],
                    'detail' => $finding['detail'],
                    'edit_url' => $finding['edit_url'] ?? null,
                    'related_edit_url' => $finding['related_edit_url'] ?? null,
                    'fix_type' => $finding['fix_type'] ?? null,
                    'fix_field' => $finding['fix_field'] ?? null,
                    'fix_mode' => $finding['fix_mode'] ?? null,
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($rows !== []) {
            AiRecommendation::upsert(
                $rows,
                ['category', 'content_type', 'content_id', 'related_content_type', 'related_content_id', 'locale'],
                ['audit_run_id', 'severity', 'title', 'detail', 'edit_url', 'related_edit_url', 'fix_type', 'fix_field', 'fix_mode', 'updated_at']
            );
        }

        $resolved = AiRecommendation::pending()
            ->get(['id', 'category', 'content_type', 'content_id', 'related_content_type', 'related_content_id', 'locale'])
            ->reject(fn ($row) => in_array($this->rowKey($row->toArray()), $freshKeys, true));

        $resolvedCount = $resolved->count();
        $resolved->each(fn ($row) => $row->delete());

        return ['found' => count($rows), 'new' => $newCount, 'resolved' => $resolvedCount];
    }

    private function rowKey(array $row): string
    {
        return implode(':', [
            $row['category'],
            $row['content_type'] ?? '',
            $row['content_id'] ?? 0,
            $row['related_content_type'] ?? '',
            $row['related_content_id'] ?? 0,
            $row['locale'] ?? '',
        ]);
    }

    /**
     * یک‌بار Article/Page واقعی همه‌ی مواردِ منتشرشده را بار می‌کند — نگاشتِ «model:id» → مدل.
     * جلوی N (یا N²، برای دسته‌های جفتی) کوئری تکراری را می‌گیرد که هر متدِ دسته با find() جداگانه
     * تولید می‌کرد.
     *
     * @return Collection<string, Article|Page>
     */
    private function loadRecords(Collection $items): Collection
    {
        $byModel = $items->groupBy('model');
        $records = collect();

        foreach (['Article' => Article::class, 'Page' => Page::class] as $model => $class) {
            $ids = $byModel->get($model, collect())->pluck('id')->all();
            if ($ids === []) {
                continue;
            }

            $class::whereIn('id', $ids)->get()->each(function ($record) use (&$records, $model) {
                $records[$model.':'.$record->id] = $record;
            });
        }

        return $records;
    }

    private function recordFrom(Collection $records, array $item): Article|Page|null
    {
        return $records->get($item['model'].':'.$item['id']);
    }

    // ============ دسته‌های بازاستفاده‌شده از سرویس‌های موجود (بدون منطق تشخیص تازه) ============

    private function reviewBasedFindings(Collection $items, Collection $records, string $reviewType, string $fixField, string $fixMode, ?string $modelFilter = null): array
    {
        $findings = [];

        foreach ($items as $item) {
            if ($modelFilter && $item['model'] !== $modelFilter) {
                continue;
            }

            $record = $this->recordFrom($records, $item);
            if (! $record) {
                continue;
            }

            $hit = collect($this->reviewService->review($record))->firstWhere('type', $reviewType);
            if (! $hit) {
                continue;
            }

            $findings[] = $this->finding($item, $hit['message'], fixType: 'field', fixField: $fixField, fixMode: $fixMode);
        }

        return $findings;
    }

    private function missingInternalLinks(Collection $nodes): array
    {
        return $nodes
            ->filter(fn ($node) => $node['status'] === 'published' && $node['outbound'] === 0)
            ->map(fn ($node) => [
                'content_type' => $node['model'],
                'content_id' => $node['id'],
                'locale' => $node['locale'],
                'severity' => 'notice',
                'title' => $node['title'].' ('.strtoupper($node['locale']).') has no outbound internal links',
                'detail' => "This content doesn't link out to any other article or page — internal links help readers and search engines discover related content.",
                'edit_url' => $node['edit_url'],
                'fix_type' => 'internal_links',
                'fix_field' => 'internal_links',
                'fix_mode' => 'generate',
            ])
            ->values()->all();
    }

    private function missingAlt(array $seoMissingAlt): array
    {
        return collect($seoMissingAlt)->map(function (array $f) {
            if ($f['type'] !== 'Media') {
                return $this->wrapFinding($f, fixType: null); // تصویر داخل متن — یک فیلد ActionRegistry واحد ندارد
            }

            $media = Media::find($f['id']);
            $owner = $media?->ownerRecord();

            if (! $owner) {
                return $this->wrapFinding($f, fixType: null);
            }

            return $this->wrapFinding(
                array_merge($f, ['id' => $owner->id, 'type' => $owner instanceof Article ? 'Article' : 'Page']),
                fixType: 'field',
                fixField: 'alt_text',
                fixMode: 'generate',
            );
        })->all();
    }

    private function poorSeo(Collection $items, Collection $records): array
    {
        $findings = [];

        foreach ($items as $item) {
            $record = $this->recordFrom($records, $item);
            if (! $record) {
                continue;
            }

            $seo = $this->reviewService->scoreCard($record)['categories']['seo'];
            if ($seo['score'] >= 70) {
                continue;
            }

            $findings[] = $this->finding(
                $item,
                'SEO score: '.$seo['score'].'/100 — '.implode(' ', $seo['issues']),
                fixType: 'field',
                fixField: 'seo_title', // نقطه‌ی شروع بسته‌ی رفع؛ داشبورد بقیه‌ی سه فیلد OG/متا را هم صف می‌کند
                fixMode: 'generate',
            );
        }

        return $findings;
    }

    private function imageOptimization(): array
    {
        return Media::where('type', 'image')->get()
            ->filter(fn (Media $media) => $media->isInUse())
            ->map(function (Media $media) {
                $warnings = array_filter($media->warnings(), fn ($w) => ! str_contains($w, 'Missing ALT')); // ALT دسته‌ی خودش را دارد
                if ($warnings === []) {
                    return null;
                }

                return [
                    'content_type' => 'Media',
                    'content_id' => $media->id,
                    'locale' => null,
                    'severity' => 'notice',
                    'title' => $media->original_name.' needs optimization',
                    'detail' => implode(' ', $warnings),
                    'edit_url' => MediaLibrary::getUrl(['media' => $media->id]),
                    'fix_type' => null, // یک فیلد متنی نمی‌تواند اندازه/حجم تصویر را عوض کند
                ];
            })
            ->filter()->values()->all();
    }

    private function needsTranslation(Collection $items, Collection $records): array
    {
        $findings = [];

        foreach ($items as $item) {
            $record = $this->recordFrom($records, $item);
            if (! $record) {
                continue;
            }

            $hasTranslation = $record->translation_of !== null || $record->translations()->exists();
            if ($hasTranslation) {
                continue;
            }

            $targetLocale = $item['locale'] === 'tr' ? 'en' : 'tr';

            $findings[] = $this->finding(
                $item,
                'No '.strtoupper($targetLocale).' version exists yet — this content has no linked translation.',
                fixType: 'translate',
                fixField: 'translate',
                fixMode: $targetLocale,
            );
        }

        return $findings;
    }

    private function wrapReviewOnly(array $seoFindings): array
    {
        return collect($seoFindings)->map(fn (array $f) => $this->wrapFinding($f, fixType: null))->all();
    }

    private function wrapFinding(array $f, ?string $fixType, ?string $fixField = null, ?string $fixMode = null): array
    {
        return [
            'content_type' => $f['type'],
            'content_id' => $f['id'] ?? null,
            'locale' => $f['locale'],
            'severity' => 'notice',
            'title' => $f['title'],
            'detail' => $f['detail'],
            'edit_url' => $f['edit_url'],
            'fix_type' => $fixType,
            'fix_field' => $fixField,
            'fix_mode' => $fixMode,
        ];
    }

    // ============ دسته‌های تازه (بدون معادل موجود) ============

    private function staleContent(Collection $items, Collection $records): array
    {
        $threshold = now()->subDays(self::STALE_DAYS);
        $findings = [];

        foreach ($items as $item) {
            $record = $this->recordFrom($records, $item);
            if (! $record || $record->updated_at === null || $record->updated_at->gte($threshold)) {
                continue;
            }

            $findings[] = $this->finding(
                $item,
                'Last updated '.$record->updated_at->diffForHumans().' — content this old often falls behind on accuracy/SEO.',
                fixType: 'field', fixField: 'body', fixMode: 'improve',
            );
        }

        return $findings;
    }

    private function thinContent(Collection $items): array
    {
        return $items
            ->map(fn ($item) => array_merge($item, ['word_count' => str_word_count(strip_tags((string) $item['body']))]))
            ->filter(fn ($item) => $item['word_count'] > 0 && $item['word_count'] < self::THIN_CONTENT_WORDS)
            ->map(fn ($item) => $this->finding(
                $item,
                'Only '.$item['word_count'].' words — thin content tends to rank and convert worse than in-depth coverage.',
                fixType: 'field', fixField: 'body', fixMode: 'expand',
            ))
            ->values()->all();
    }

    private function weakParagraph(Collection $items, bool $first): array
    {
        $threshold = $first ? self::WEAK_INTRO_WORDS : self::WEAK_CONCLUSION_WORDS;
        $label = $first ? 'introduction' : 'conclusion';

        $findings = [];

        foreach ($items as $item) {
            $paragraphs = $this->scanner->paragraphs($item['body']);
            if ($paragraphs === []) {
                continue;
            }

            $target = $first ? $paragraphs[array_key_first($paragraphs)] : $paragraphs[array_key_last($paragraphs)];

            if ($target['word_count'] >= $threshold) {
                continue;
            }

            $findings[] = $this->finding(
                $item,
                'The '.$label." is only {$target['word_count']} words — ".($first
                    ? 'a strong opening hooks the reader and signals relevance to search engines.'
                    : 'a strong closing reinforces the topic and leads into a call-to-action.'),
                fixType: 'field', fixField: 'body', fixMode: 'improve',
            );
        }

        return $findings;
    }

    private function duplicateTopics(Collection $items, Collection $records): array
    {
        $findings = [];
        $byGroup = $items->groupBy(fn ($item) => $item['model'].':'.$item['locale']);

        foreach ($byGroup as $group) {
            $list = $group->values();

            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];

                    // زوج‌های ترجمه (یکی translation_of دیگری، در هر جهت) عمداً معاف‌اند — قرار است هم‌تاپیک باشند
                    $recordA = $this->recordFrom($records, $a);
                    $recordB = $this->recordFrom($records, $b);
                    $isTranslationPair = ($recordA && (int) $recordA->translation_of === (int) $b['id'])
                        || ($recordB && (int) $recordB->translation_of === (int) $a['id']);

                    if ($isTranslationPair) {
                        continue;
                    }

                    $similarity = $this->jaccard($a['title'], $b['title']);
                    if ($similarity < self::DUPLICATE_TOPIC_JACCARD) {
                        continue;
                    }

                    $findings[] = [
                        'content_type' => $a['model'],
                        'content_id' => $a['id'],
                        'related_content_type' => $b['model'],
                        'related_content_id' => $b['id'],
                        'locale' => $a['locale'],
                        'severity' => 'notice',
                        'title' => 'Possible duplicate topic: "'.$a['title'].'" / "'.$b['title'].'"',
                        'detail' => 'These two titles overlap significantly ('.round($similarity * 100).'% word overlap) — consider merging them or differentiating their angle to avoid confusing readers and search engines.',
                        'edit_url' => $a['edit_url'],
                        'related_edit_url' => $b['edit_url'],
                        'fix_type' => null, // تصمیم ادغام/تمایز، قضاوت سردبیری است — نگاه کنید به CLAUDE.md، «هیچ سیستم ریدایرکتی وجود ندارد»
                    ];
                }
            }
        }

        return $findings;
    }

    private function contentCannibalization(): array
    {
        $findings = [];

        $keywords = Keyword::where('keywordable_type', 'Article')
            ->with('keywordable')
            ->get()
            ->filter(fn (Keyword $k) => $k->keywordable && $k->keywordable->status === 'published');

        $groups = $keywords->groupBy(fn (Keyword $k) => $k->keywordable->locale.':'.mb_strtolower(trim($k->keyword)));

        foreach ($groups as $key => $group) {
            $articles = $group->pluck('keywordable')->unique('id')->values();
            if ($articles->count() < 2) {
                continue;
            }

            [$locale, $keyword] = explode(':', $key, 2);

            for ($i = 0; $i < $articles->count(); $i++) {
                for ($j = $i + 1; $j < $articles->count(); $j++) {
                    $a = $articles[$i];
                    $b = $articles[$j];

                    $findings[] = [
                        'content_type' => 'Article',
                        'content_id' => $a->id,
                        'related_content_type' => 'Article',
                        'related_content_id' => $b->id,
                        'locale' => $locale,
                        'severity' => 'warning',
                        'title' => 'Keyword cannibalization: "'.$keyword.'"',
                        'detail' => '"'.$a->title.'" and "'.$b->title.'" both target the keyword "'.$keyword.'" — competing for the same search intent can split ranking signals instead of strengthening either page.',
                        'edit_url' => ArticleResource::getUrl('edit', ['record' => $a->id]),
                        'related_edit_url' => ArticleResource::getUrl('edit', ['record' => $b->id]),
                        'fix_type' => null, // قضاوت سردبیری (ادغام/تمایز محتوا)، نه چیزی که هوش مصنوعی خودکار تصمیم بگیرد
                    ];
                }
            }
        }

        return $findings;
    }

    private function jaccard(string $a, string $b): float
    {
        $wordsA = $this->significantWords($a);
        $wordsB = $this->significantWords($b);

        if ($wordsA === [] || $wordsB === []) {
            return 0.0;
        }

        $intersection = count(array_intersect($wordsA, $wordsB));
        $union = count(array_unique(array_merge($wordsA, $wordsB)));

        return $union > 0 ? $intersection / $union : 0.0;
    }

    private function significantWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) > 3 && ! in_array($w, self::STOPWORDS, true)
        )));
    }

    private function finding(array $item, string $detail, ?string $fixType, ?string $fixField = null, ?string $fixMode = null): array
    {
        return [
            'content_type' => $item['model'],
            'content_id' => $item['id'],
            'locale' => $item['locale'],
            'severity' => 'notice',
            'title' => $item['title'].' ('.strtoupper($item['locale']).')',
            'detail' => $detail,
            'edit_url' => $item['edit_url'],
            'fix_type' => $fixType,
            'fix_field' => $fixField,
            'fix_mode' => $fixMode,
        ];
    }
}
