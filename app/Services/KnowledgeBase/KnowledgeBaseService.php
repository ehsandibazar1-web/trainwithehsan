<?php

namespace App\Services\KnowledgeBase;

use App\Models\KnowledgeEntry;
use App\Services\AiAssistant\ProviderManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * بازیابی «فقط ورودی‌های واقعاً مرتبط» از حافظه‌ی دانش قبل از هر تولید محتوا — بر خلاف
 * App\Services\BrandMemory\BrandMemoryService (که همیشه یک بلوک ثابت برمی‌گرداند)، اینجا فقط
 * زیرمجموعه‌ای که به همان تولید مشخص مربوط است انتخاب می‌شود. مراحل: (۱) ورودی‌های pin‌شده
 * همیشه واردند، (۲) یک پیش‌فیلترِ ارزان بر اساس هم‌پوشانی کلمه/برچسب/اولویت، (۳) اگر
 * ارائه‌دهنده‌ی هوش مصنوعی در دسترس باشد، از همان ProviderManager موجود (دقیقاً همان مسیری که
 * ContentAssistantService::classifyIntent() استفاده می‌کند) برای رتبه‌بندی نهاییِ «واقعاً
 * مرتبط» بین نامزدها استفاده می‌شود — بدون هیچ وابستگی تازه‌ای مثل پایگاه‌داده‌ی برداری؛ در غیر
 * این صورت رتبه‌بندی کلمه‌ای به‌تنهایی جایگزین می‌شود و تولید محتوا هرگز مسدود نمی‌شود.
 */
class KnowledgeBaseService
{
    private const MAX_CANDIDATES_FOR_AI_RANKING = 15;

    private const DEFAULT_LIMIT = 5;

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'can', 'will', 'about', 'into', 'more', 'than',
        'them', 'their', 'what', 'when', 'where', 'which', 'while', 'who', 'why', 'how', 'here',
        'there', 'these', 'those', 'also', 'just', 'like', 'over', 'each', 'some', 'such',
        'için', 'veya', 'çok', 'daha', 'gibi', 'ama', 'ile', 'olan', 'olarak', 'bir', 'bu', 'şu',
        've', 'de', 'da', 'ne', 'ise', 'ki', 'mi', 'mu', 'mü',
    ];

    public function __construct(private readonly ProviderManager $providerManager) {}

    /**
     * @return Collection<int, KnowledgeEntry>
     */
    public function retrieveRelevant(string $query, string $locale, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $pinned = KnowledgeEntry::query()
            ->available()
            ->where('locale', $locale)
            ->where('is_pinned', true)
            ->with('tags')
            ->get();

        if ($pinned->count() >= $limit) {
            return $pinned->take($limit)->values();
        }

        $remaining = $limit - $pinned->count();

        $candidates = KnowledgeEntry::query()
            ->available()
            ->where('locale', $locale)
            ->where('is_pinned', false)
            ->with('tags')
            ->get();

        if ($candidates->isEmpty()) {
            return $pinned->values();
        }

        $shortlist = $this->scoreByKeywordOverlap($query, $candidates)
            ->sortByDesc('score')
            ->take(self::MAX_CANDIDATES_FOR_AI_RANKING)
            ->pluck('entry')
            ->values();

        $selected = $this->rankWithAi($query, $shortlist) ?? $shortlist;

        return $pinned->concat($selected->take($remaining))->values();
    }

    /**
     * @return Collection<int, array{entry: KnowledgeEntry, score: int}>
     */
    private function scoreByKeywordOverlap(string $query, Collection $candidates): Collection
    {
        $queryWords = $this->significantWords($query);

        return $candidates->map(function (KnowledgeEntry $entry) use ($queryWords) {
            $overlap = $queryWords === []
                ? 0
                : count(array_intersect($queryWords, $this->significantWords($entry->title.' '.$entry->category.' '.$entry->content)));

            $tagOverlap = $entry->tags->pluck('name')
                ->filter(fn (string $name) => str_contains(Str::lower($query), Str::lower($name)))
                ->count();

            $score = ($overlap * 2) + ($tagOverlap * 3) + $this->priorityWeight($entry);

            return ['entry' => $entry, 'score' => $score];
        });
    }

    private function priorityWeight(KnowledgeEntry $entry): int
    {
        return match ($entry->priority) {
            KnowledgeEntry::PRIORITY_CRITICAL => 4,
            KnowledgeEntry::PRIORITY_HIGH => 2,
            KnowledgeEntry::PRIORITY_LOW => -1,
            default => 0,
        };
    }

    /** @return string[] */
    private function significantWords(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($text)) ?: [];
        $words = array_filter($words, fn (string $w) => mb_strlen($w) > 3 && ! in_array($w, self::STOPWORDS, true));

        return array_values(array_unique($words));
    }

    /**
     * از همان ProviderManager موجود برای انتخاب «واقعاً مرتبط» بین نامزدهای پیش‌فیلترشده
     * استفاده می‌کند — این همان چیزی است که «semantic retrieval» یعنی، بدون افزودن پایگاه‌داده‌ی
     * برداری تازه. اگر ارائه‌دهنده در دسترس نباشد یا پاسخ قابل‌استفاده نباشد null برمی‌گرداند تا
     * فراخوان به رتبه‌بندی کلمه‌ای برگردد — هرگز تولید محتوا را مسدود نمی‌کند. آرایه‌ی خالیِ
     * واقعی (هوش مصنوعی صراحتاً گفته «هیچ‌کدام مرتبط نیست») از fallback جدا نگه داشته می‌شود.
     *
     * @return ?Collection<int, KnowledgeEntry>
     */
    private function rankWithAi(string $query, Collection $shortlist): ?Collection
    {
        if ($shortlist->isEmpty()) {
            return $shortlist;
        }

        $list = $shortlist
            ->map(fn (KnowledgeEntry $entry, int $i) => ($i + 1).'. [id:'.$entry->id.'] '.$entry->title.' — '.Str::limit(strip_tags($entry->content), 150))
            ->implode("\n");

        $system = 'You select which knowledge-base entries are genuinely relevant background for writing a specific piece of content. Return ONLY a JSON array of the relevant entry IDs (integers), most relevant first — no explanation, no markdown fences. If none are relevant, return an empty array [].';
        $user = "Content brief: {$query}\n\nCandidate knowledge entries:\n{$list}";

        try {
            $raw = $this->providerManager->respond($system, $user, [], ['max_tokens' => 300], actionKey: 'knowledge_base.retrieve');
        } catch (\Throwable) {
            return null;
        }

        $stripped = preg_replace('/^```(json)?|```$/m', '', trim($raw));
        $ids = json_decode(trim((string) $stripped), true);

        if (! is_array($ids)) {
            return null;
        }

        if ($ids === []) {
            return collect();
        }

        $byId = $shortlist->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $byId->get((int) $id))
            ->filter()
            ->values();
    }
}
