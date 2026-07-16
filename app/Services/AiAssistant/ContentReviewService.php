<?php

namespace App\Services\AiAssistant;

use App\Models\Article;
use App\Models\Media;
use App\Services\InternalLinking\LinkGraphService;
use App\Services\Seo\HtmlContentScanner;
use App\Services\Seo\InternalLinkResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * ممیزی محتوایی یک مقاله/صفحه‌ی مشخص — همه‌ی بررسی‌ها قطعی و محلی‌اند (بدون تماس هوش مصنوعی)،
 * همان روحیه‌ی SeoAuditService: سریع، رایگان، و همیشه در دسترس. HtmlContentScanner و
 * InternalLinkResolver بازاستفاده می‌شوند، نه پارسر جدید.
 */
class ContentReviewService
{
    private const LONG_PARAGRAPH_WORDS = 150;

    private const FAQ_OPPORTUNITY_BODY_WORDS = 600;

    private const CTA_PHRASES = [
        'book', 'contact', 'sign up', 'signup', 'join', 'get in touch', 'schedule',
        'try a class', 'call us', 'message us', 'enroll', 'reserve',
        'i̇letişim', 'iletişim', 'kayıt', 'katıl', 'randevu',
    ];

    public function __construct(
        private readonly HtmlContentScanner $scanner,
        private readonly InternalLinkResolver $resolver,
        private readonly LinkGraphService $linkGraph,
    ) {}

    /**
     * @return array<int, array{type: string, message: string, severity: string}>
     */
    public function review(Model $record): array
    {
        $body = (string) $record->body;
        $findings = [];

        $headings = $this->scanner->headings($body);

        if ($headings === []) {
            $findings[] = $this->finding('missing_headings', 'This content has no headings (H2/H3) — search engines and readers rely on headings to scan the structure.', 'warning');
        } elseif ($headings[0]['level'] > 2) {
            $findings[] = $this->finding('missing_headings', 'The first heading is H'.$headings[0]['level'].' — starting with H2 is the expected structure for article body headings.', 'notice');
        }

        $paragraphs = $this->scanner->paragraphs($body);

        foreach ($paragraphs as $i => $paragraph) {
            if ($paragraph['word_count'] > self::LONG_PARAGRAPH_WORDS) {
                $findings[] = $this->finding('long_paragraph', 'Paragraph '.($i + 1).' is '.$paragraph['word_count'].' words — consider breaking it up for readability.', 'notice');
            }
        }

        [$hasInternal, $hasExternal] = $this->linkCoverage($body);

        if (! $hasInternal) {
            $findings[] = $this->finding('missing_internal_links', 'No internal links found — link to other relevant articles/pages to help readers and search engines discover them.', 'warning');
        }

        if (! $hasExternal) {
            $findings[] = $this->finding('missing_external_links', 'No external links found — linking to a credible outside source can strengthen trust and SEO.', 'notice');
        }

        foreach ($this->scanner->images($body) as $image) {
            if ($image['alt'] === '') {
                $findings[] = $this->finding('missing_alt_text', 'An image is missing ALT text ('.$image['src'].').', 'warning');
            }
        }

        foreach ($this->duplicateKeywords($record) as $keyword) {
            $findings[] = $this->finding('duplicate_keywords', 'The keyword "'.$keyword.'" is listed more than once.', 'notice');
        }

        if ($record instanceof Article && empty($record->faqs)) {
            $bodyWordCount = count(preg_split('/\s+/u', trim(strip_tags($body)), -1, PREG_SPLIT_NO_EMPTY));

            if ($bodyWordCount > self::FAQ_OPPORTUNITY_BODY_WORDS) {
                $findings[] = $this->finding('missing_faq_opportunity', 'This is a long, in-depth article ('.$bodyWordCount.' words) with no FAQ section — consider adding one, both for readers and for FAQ rich-result eligibility.', 'notice');
            }
        }

        if (! $this->hasCallToAction($paragraphs)) {
            $findings[] = $this->finding('weak_cta', 'No clear call-to-action detected near the end of the content — consider ending with a direct next step for the reader.', 'notice');
        }

        return $findings;
    }

    /**
     * @return array{bool, bool} [hasInternal, hasExternal]
     */
    private function linkCoverage(string $body): array
    {
        $hasInternal = false;
        $hasExternal = false;

        foreach ($this->scanner->links($body) as $link) {
            if ($this->resolver->isSkippable($link['href'])) {
                continue;
            }

            if ($this->resolver->isExternal($link['href'])) {
                $hasExternal = true;
            } else {
                $hasInternal = true;
            }
        }

        return [$hasInternal, $hasExternal];
    }

    /** @return array<int, string> */
    private function duplicateKeywords(Model $record): array
    {
        $keywords = $record->keywords()->pluck('keyword')->map(fn ($k) => mb_strtolower(trim($k)));

        return $keywords->duplicates()->unique()->values()->all();
    }

    /** @param  array<int, array{text: string, word_count: int}>  $paragraphs */
    private function hasCallToAction(array $paragraphs): bool
    {
        $tail = mb_strtolower(collect($paragraphs)->pluck('text')->slice(-2)->implode(' '));

        foreach (self::CTA_PHRASES as $phrase) {
            if (str_contains($tail, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function finding(string $type, string $message, string $severity): array
    {
        return ['type' => $type, 'message' => $message, 'severity' => $severity];
    }

    /**
     * کارت امتیاز سلامت محتوا — هر شش دسته از سرویس‌های موجود و بدون تغییر محاسبه می‌شود (نگاه کنید
     * به CLAUDE.md، بخش «AI Content Assistant»)؛ اینجا فقط ریاضیِ امتیازدهی است، نه بررسی جدید.
     * overall = میانگین ساده‌ی شش دسته — بدون وزن‌دهی پنهان، هم‌روح با توضیح‌های صریح SuggestionEngine.
     *
     * @return array{overall: int, categories: array<string, array{label: string, score: int, issues: array<int, string>}>}
     */
    public function scoreCard(Model $record): array
    {
        $categories = [
            'seo' => $this->seoScore($record),
            'readability' => $this->readabilityScore($record),
            'content_quality' => $this->contentQualityScore($record),
            'internal_linking' => $this->internalLinkingScore($record),
            'media_optimization' => $this->mediaOptimizationScore($record),
            'schema' => $this->schemaScore($record),
        ];

        $overall = (int) round(collect($categories)->avg(fn (array $c) => $c['score']));

        return ['overall' => $overall, 'categories' => $categories];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function seoScore(Model $record): array
    {
        $score = 100;
        $issues = [];

        if (blank($record->seo_title)) {
            $score -= 25;
            $issues[] = 'SEO title not set — the page title is used as a fallback.';
        }

        if (blank($record->meta_description) || mb_strlen((string) $record->meta_description) < 50) {
            $score -= 25;
            $issues[] = 'Meta description missing or shorter than 50 characters.';
        }

        if (blank($record->og_title)) {
            $score -= 25;
            $issues[] = 'Open Graph title not set — used when this is shared on social media.';
        }

        if (blank($record->og_description)) {
            $score -= 25;
            $issues[] = 'Open Graph description not set — used when this is shared on social media.';
        }

        return ['label' => 'SEO', 'score' => max(0, $score), 'issues' => $issues];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function readabilityScore(Model $record): array
    {
        $findings = collect($this->review($record))
            ->whereIn('type', ['missing_headings', 'long_paragraph']);

        $score = 100 - $findings->where('type', 'missing_headings')->count() * 25
            - $findings->where('type', 'long_paragraph')->count() * 10;

        return ['label' => 'Readability', 'score' => max(0, $score), 'issues' => $findings->pluck('message')->all()];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function contentQualityScore(Model $record): array
    {
        $findings = collect($this->review($record))
            ->whereIn('type', ['missing_faq_opportunity', 'weak_cta']);

        $score = 100 - $findings->count() * 20;

        return ['label' => 'Content Quality', 'score' => max(0, $score), 'issues' => $findings->pluck('message')->all()];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function internalLinkingScore(Model $record): array
    {
        $recordType = $record instanceof Article ? 'Article' : 'Page';
        $nodes = $this->linkGraph->build()['nodes'];
        $node = $nodes->get($this->linkGraph->nodeKey($recordType, $record->id));

        $inbound = $node['inbound'] ?? 0;
        $outbound = $node['outbound'] ?? 0;

        $score = 100;
        $issues = [];

        if ($inbound === 0) {
            $score -= 50;
            $issues[] = 'No other article or page links here yet (orphaned).';
        } elseif ($inbound < 2) {
            $score -= 20;
            $issues[] = "Only {$inbound} internal link points here — aim for at least 2.";
        }

        if ($outbound === 0) {
            $score -= 30;
            $issues[] = "This content doesn't link out to any other article or page.";
        }

        return ['label' => 'Internal Linking', 'score' => max(0, $score), 'issues' => $issues];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function mediaOptimizationScore(Model $record): array
    {
        if (blank($record->image_path)) {
            return ['label' => 'Media Optimization', 'score' => 50, 'issues' => ['No featured image set.']];
        }

        $media = Media::where('disk_path', $record->image_path)->first();

        if (! $media) {
            return [
                'label' => 'Media Optimization',
                'score' => 60,
                'issues' => ["Featured image isn't tracked in the Media Library — no ALT text or usage data available."],
            ];
        }

        $warnings = $media->warnings();

        return [
            'label' => 'Media Optimization',
            'score' => max(0, 100 - count($warnings) * 20),
            'issues' => $warnings,
        ];
    }

    /** @return array{label: string, score: int, issues: array<int, string>} */
    private function schemaScore(Model $record): array
    {
        if ($record instanceof Article) {
            return ['label' => 'Schema', 'score' => 100, 'issues' => []];
        }

        return [
            'label' => 'Schema',
            'score' => 40,
            'issues' => ['Standalone pages have no JSON-LD template — this is a template-level gap, not fixable by editing this page.'],
        ];
    }
}
