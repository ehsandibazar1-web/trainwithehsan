<?php

namespace App\Services\InternalLinking;

use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * پیشنهاد لینک داخلی — یک امتیازدهیِ قانون‌محور (rule-based) است، نه هوش مصنوعی/یادگیری ماشین
 * (این پروژه هیچ اتصال به سرویس LLM ندارد و «AI Studio» موجود فقط برای ایمپورت محتواست، نه
 * پیشنهاد لینک — نگاه کنید به CLAUDE.md). سه سیگنالِ قابل‌توضیح با هم جمع می‌شوند:
 *   ۱) هم‌پوشانی کلیدواژه‌ی هدف (بیشترین وزن)
 *   ۲) هم‌دسته‌بودن (ستون category در Article)
 *   ۳) شباهت متنی ساده (هم‌پوشانی کلمات معنادار عنوان/توضیح، Jaccard)
 * تا امتیاز اطمینان ۰ تا ۱۰۰ بسازند.
 */
class SuggestionEngine
{
    // پیشنهادهایی با اطمینان کمتر از این، اصلا نشان داده نمی‌شوند (نویز محسوب می‌شوند)
    private const MIN_CONFIDENCE = 30;

    // حداکثر چند منبع پیشنهادی برای هر هدفِ کم‌لینک
    private const MAX_SUGGESTIONS_PER_TARGET = 3;

    private const KEYWORD_WEIGHT = 50;

    private const CATEGORY_WEIGHT = 25;

    private const TEXT_SIMILARITY_WEIGHT = 25;

    // یک هدف با کمتر از این تعداد لینک ورودی «نیازمند لینک» در نظر گرفته می‌شود (orphan یا weak)
    private const NEEDS_LINKS_THRESHOLD = 2;

    private const STOPWORDS = [
        // انگلیسی
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'can', 'will', 'about', 'into', 'more', 'than',
        'them', 'their', 'what', 'when', 'where', 'which', 'while', 'who', 'why', 'how', 'here',
        'there', 'these', 'those', 'also', 'just', 'like', 'over', 'each', 'some', 'such',
        // ترکی
        'için', 'veya', 'çok', 'daha', 'gibi', 'ama', 'ile', 'olan', 'olarak', 'bir', 'bu', 'şu',
        've', 'de', 'da', 'ne', 'ise', 'ki', 'mi', 'mu', 'mü',
    ];

    public function __construct(private readonly LinkGraphService $graphService) {}

    /**
     * محاسبه‌ی پیشنهادها — چیزی ذخیره نمی‌کند، فقط برمی‌گرداند (تست‌پذیرتر از یک متد ذخیره‌کننده).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function suggest(): Collection
    {
        $graph = $this->graphService->build();
        $nodes = $graph['nodes'];
        $keywordsByNode = $this->loadKeywords($nodes);

        $needsLinks = $nodes->filter(
            fn ($node) => $node['status'] === 'published' && $node['inbound'] < self::NEEDS_LINKS_THRESHOLD
        );

        $suggestions = collect();

        foreach ($needsLinks as $targetKey => $target) {
            $alreadyLinkedFrom = $target['inbound_from'] ?? [];

            $scored = $nodes
                ->reject(fn ($node, $key) => $key === $targetKey)
                ->filter(fn ($node) => $node['locale'] === $target['locale'])
                ->reject(fn ($node, $key) => in_array($key, $alreadyLinkedFrom, true))
                ->map(fn ($candidate) => $this->score($candidate, $target, $keywordsByNode))
                ->filter(fn ($s) => $s['confidence'] >= self::MIN_CONFIDENCE)
                ->sortByDesc('confidence')
                ->take(self::MAX_SUGGESTIONS_PER_TARGET);

            $suggestions = $suggestions->concat($scored->values());
        }

        return $suggestions->values();
    }

    /**
     * پیشنهادها را محاسبه و در دیتابیس persist می‌کند — تصمیم‌های قبلی ادمین (approved/dismissed)
     * را دست نمی‌زند، فقط ردیف‌های pending را به‌روز/حذف می‌کند. برای اجرای async از
     * App\Jobs\GenerateInternalLinkSuggestions فراخوانی می‌شود («use queues where appropriate»).
     */
    public function generateAndPersist(): int
    {
        $fresh = $this->suggest();

        $decided = InternalLinkSuggestion::whereIn('status', ['approved', 'dismissed'])
            ->get(['source_type', 'source_id', 'target_type', 'target_id'])
            ->map(fn ($row) => $this->pairKey($row->source_type, $row->source_id, $row->target_type, $row->target_id))
            ->flip();

        $now = now();
        $rows = [];
        $freshPairKeys = [];

        foreach ($fresh as $s) {
            $key = $this->pairKey($s['source']['model'], $s['source']['id'], $s['target']['model'], $s['target']['id']);
            $freshPairKeys[] = $key;

            if ($decided->has($key)) {
                continue; // ادمین قبلا تصمیم گرفته — دست نمی‌زنیم
            }

            $rows[] = [
                'source_type' => $s['source']['model'],
                'source_id' => $s['source']['id'],
                'target_type' => $s['target']['model'],
                'target_id' => $s['target']['id'],
                'locale' => $s['target']['locale'],
                'confidence_score' => $s['confidence'],
                'recommended_anchor_text' => $s['anchor'],
                'reason' => $s['reason'],
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            InternalLinkSuggestion::upsert(
                $rows,
                ['source_type', 'source_id', 'target_type', 'target_id'],
                ['locale', 'confidence_score', 'recommended_anchor_text', 'reason', 'updated_at']
            );
        }

        // پیشنهادهای pending که دیگر در محاسبه‌ی تازه نیستند (چون محتوا/کلیدواژه عوض شده) پاک شوند
        InternalLinkSuggestion::pending()
            ->get(['id', 'source_type', 'source_id', 'target_type', 'target_id'])
            ->reject(fn ($row) => in_array(
                $this->pairKey($row->source_type, $row->source_id, $row->target_type, $row->target_id),
                $freshPairKeys,
                true
            ))
            ->each(fn ($row) => $row->delete());

        return count($rows);
    }

    private function pairKey(string $sourceType, int $sourceId, string $targetType, int $targetId): string
    {
        return "{$sourceType}:{$sourceId}:{$targetType}:{$targetId}";
    }

    /**
     * @return array<string, array<int, string>> نگاشتِ کلید گره → فهرست کلیدواژه‌های آن
     */
    private function loadKeywords(Collection $nodes): array
    {
        $byType = $nodes->groupBy('model');
        $map = [];

        foreach ($byType as $type => $group) {
            $modelClass = $type === 'Article' ? Article::class : Page::class;
            $ids = $group->pluck('id')->all();

            $modelClass::query()
                ->whereIn('id', $ids)
                ->with('keywords:id,keywordable_type,keywordable_id,keyword')
                ->get(['id'])
                ->each(function ($model) use (&$map, $type) {
                    $map[$this->graphService->nodeKey($type, $model->id)] = $model->keywords->pluck('keyword')->all();
                });
        }

        return $map;
    }

    /**
     * @return array{source: array, target: array, confidence: int, anchor: string, reason: string}
     */
    private function score(array $candidate, array $target, array $keywordsByNode): array
    {
        $targetKeywords = $keywordsByNode[$this->graphService->nodeKey($target['model'], $target['id'])] ?? [];

        [$keywordScore, $matchedKeyword] = $this->keywordOverlapScore($candidate, $targetKeywords);
        $categoryScore = $this->categoryMatchScore($candidate, $target);
        $textScore = $this->textSimilarityScore($candidate, $target);

        $confidence = min(100, $keywordScore + $categoryScore + $textScore);

        $anchor = $matchedKeyword ?: ($targetKeywords[0] ?? $target['title']);

        $reasons = [];
        if ($keywordScore > 0) {
            $reasons[] = "already mentions the target keyword \"{$matchedKeyword}\"";
        }
        if ($categoryScore > 0) {
            $reasons[] = "shares the \"{$candidate['category']}\" category";
        }
        if ($textScore > 0) {
            $reasons[] = 'has similar wording/topic';
        }

        return [
            'source' => $candidate,
            'target' => $target,
            'confidence' => $confidence,
            'anchor' => Str::limit($anchor, 60, ''),
            'reason' => 'Suggested because this content '.(($reasons !== []) ? implode(' and ', $reasons) : 'is topically related').'.',
        ];
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function keywordOverlapScore(array $candidate, array $targetKeywords): array
    {
        if (empty($targetKeywords)) {
            return [0, null];
        }

        $haystack = mb_strtolower($candidate['title'].' '.strip_tags($candidate['body'] ?? ''));

        foreach ($targetKeywords as $keyword) {
            $needle = mb_strtolower(trim((string) $keyword));
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return [self::KEYWORD_WEIGHT, $keyword];
            }
        }

        return [0, null];
    }

    private function categoryMatchScore(array $candidate, array $target): int
    {
        if (blank($candidate['category'] ?? null) || blank($target['category'] ?? null)) {
            return 0;
        }

        return mb_strtolower(trim($candidate['category'])) === mb_strtolower(trim($target['category']))
            ? self::CATEGORY_WEIGHT
            : 0;
    }

    private function textSimilarityScore(array $candidate, array $target): int
    {
        $a = $this->significantWords($candidate['title'].' '.$candidate['raw_description']);
        $b = $this->significantWords($target['title'].' '.$target['raw_description']);

        if ($a === [] || $b === []) {
            return 0;
        }

        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));

        $jaccard = $union > 0 ? $intersection / $union : 0;

        return (int) round($jaccard * self::TEXT_SIMILARITY_WEIGHT);
    }

    /**
     * @return array<int, string>
     */
    private function significantWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = array_filter($words, fn ($w) => mb_strlen($w) > 3 && ! in_array($w, self::STOPWORDS, true));

        return array_values(array_unique($words));
    }
}
