<?php

namespace App\Services\KnowledgeBase;

use App\Models\KnowledgeEntry;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Rag\Contracts\VectorStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * بازیابی «فقط chunkهای واقعا مرتبط» از حافظه‌ی دانش قبل از هر تولید محتوا — بر خلاف
 * App\Services\BrandMemory\BrandMemoryService (که همیشه یک بلوک ثابت برمی‌گرداند)، اینجا فقط
 * زیرمجموعه‌ای که به همان تولید مشخص مربوط است انتخاب می‌شود، و در سطح chunk نه کل ورودی —
 * هرگز کل Knowledge Base به هوش مصنوعی فرستاده نمی‌شود (نگاه کنید به CLAUDE.md، «RAG»).
 *
 * retrieveChunks() نقطه‌ی ورودیِ اصلی است: (۱) ورودی‌های pin‌شده همیشه واردند (یک chunk نماینده
 * به‌ازای هر کدام، نه همه‌ی chunkهایشان — وگرنه یک ورودیِ pin‌شده‌ی بزرگ می‌تواند کل حجم را پر
 * کند)، (۲) برای باقیِ ظرفیت، جست‌وجوی معنایی واقعی از طریق App\Services\Rag\Contracts\VectorStore
 * روی بردارهای از‌پیش‌ایندکس‌شده (App\Services\Rag\IndexingService)، (۳) اگر هیچ ارائه‌دهنده‌ی
 * embedding پیکربندی نشده یا چیزی هنوز ایندکس نشده، به همان بازیابیِ کلمه‌ای + رتبه‌بندیِ
 * هوش‌مصنوعیِ قبلی (سطح کل ورودی، بسته‌بندی‌شده به‌شکل یک pseudo-chunk به‌ازای هر ورودی)
 * برمی‌گردد — تولید محتوا هرگز به‌خاطر یک شکست/پیکربندی‌نشدنِ RAG مسدود نمی‌شود.
 *
 * retrieveRelevant() (امضای قدیمی، برای سازگاریِ عقب‌رو با ContentAssistantService::generate()
 * و pivot ای که هر تولید را به ورودی‌های استفاده‌شده وصل می‌کند) اکنون رویِ retrieveChunks() ساخته
 * می‌شود — همان مجموعه‌ی ورودی‌ها را برمی‌گرداند، فقط استخراج‌شده از chunkهای واقعا انتخاب‌شده.
 */
class KnowledgeBaseService
{
    private const MAX_CANDIDATES_FOR_AI_RANKING = 15;

    private const DEFAULT_LIMIT = 5;

    private const DEFAULT_CHUNK_LIMIT = 8;

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'this', 'that', 'from', 'your', 'you', 'are', 'was', 'were',
        'have', 'has', 'had', 'not', 'but', 'all', 'can', 'will', 'about', 'into', 'more', 'than',
        'them', 'their', 'what', 'when', 'where', 'which', 'while', 'who', 'why', 'how', 'here',
        'there', 'these', 'those', 'also', 'just', 'like', 'over', 'each', 'some', 'such',
        'için', 'veya', 'çok', 'daha', 'gibi', 'ama', 'ile', 'olan', 'olarak', 'bir', 'bu', 'şu',
        've', 'de', 'da', 'ne', 'ise', 'ki', 'mi', 'mu', 'mü',
    ];

    public function __construct(
        private readonly ProviderManager $providerManager,
        private readonly VectorStore $vectorStore,
    ) {}

    /**
     * بازیابیِ chunk-محورِ RAG — نقطه‌ی ورودیِ اصلی برای پرامپت‌های تولید (ContentAssistantService)
     * و برای نمایشِ «Retrieved chunks / Confidence score / Sources used» در UI.
     *
     * @return array<int, array{
     *     chunk_id: ?int, knowledge_entry_id: int, entry_title: string, source: string,
     *     chunkable_type: string, chunkable_id: int, text: string, score: float, pinned: bool,
     * }>
     */
    public function retrieveChunks(string $query, string $locale, int $limit = self::DEFAULT_CHUNK_LIMIT): array
    {
        $queryEmbedding = $this->embedQuery($query);

        $pinnedEntries = KnowledgeEntry::query()
            ->available()
            ->where('locale', $locale)
            ->where('is_pinned', true)
            ->get();

        $pinnedResults = $pinnedEntries
            ->map(fn (KnowledgeEntry $entry) => $this->pinnedChunk($entry, $queryEmbedding))
            ->values();

        if ($pinnedResults->count() >= $limit) {
            return $pinnedResults->take($limit)->all();
        }

        $remaining = $limit - $pinnedResults->count();
        $excludedIds = $pinnedEntries->pluck('id')->all();

        $semantic = $queryEmbedding
            ? $this->semanticChunkSearch($queryEmbedding, $locale, $excludedIds, $remaining)
            : [];

        // جست‌وجوی معنایی «هیچ» برمی‌گرداند هم وقتی چیزی مرتبط نیست و هم وقتی چیزی اصلاً ایندکس
        // نشده — بر خلاف rankWithAi (که [] صریح را یک قضاوتِ واقعیِ هوش مصنوعی می‌داند)، اینجا یک
        // نتیجه‌ی خالی همیشه یعنی «به بازیابیِ کلمه‌ای برگرد»، چون بازیابیِ کلمه‌ای اصلاً به
        // ایندکس‌شدنِ چیزی وابسته نیست و همیشه بهتر از نمایش هیچ‌چیز است.
        $results = $semantic !== []
            ? $semantic
            : $this->keywordFallbackChunks($query, $locale, $excludedIds, $remaining);

        return $pinnedResults->concat($results)->take($limit)->values()->all();
    }

    /**
     * امضای قدیمی — برای callerهای موجود (ContentAssistantService::generate()) که هنوز
     * Collection<KnowledgeEntry> می‌خواهند، نه chunk خام. اکنون فقط یک لایه‌ی نازک روی
     * retrieveChunks() است.
     *
     * @return Collection<int, KnowledgeEntry>
     */
    public function retrieveRelevant(string $query, string $locale, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $chunks = $this->retrieveChunks($query, $locale, max($limit, self::DEFAULT_CHUNK_LIMIT));

        $entryIds = collect($chunks)->pluck('knowledge_entry_id')->unique()->take($limit)->values()->all();

        if ($entryIds === []) {
            return collect();
        }

        $byId = KnowledgeEntry::query()->whereIn('id', $entryIds)->get()->keyBy('id');

        return collect($entryIds)->map(fn ($id) => $byId->get($id))->filter()->values();
    }

    /**
     * embedding کوئری — شکست (بدون ارائه‌دهنده‌ی پیکربندی‌شده، تماس شبکه شکست خورد) هرگز throw
     * نمی‌کند؛ null یعنی «به fallback کلمه‌ای برو»، هم‌روحِ rankWithAi زیر.
     */
    private function embedQuery(string $query): ?array
    {
        try {
            $vectors = $this->providerManager->embed([$query]);

            return $vectors[0] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * یک chunk نماینده برای یک ورودیِ pin‌شده — نه همه‌ی chunkهایش (وگرنه یک سند بزرگِ pin‌شده
     * می‌تواند تنها چیزی باشد که در پرامپت جا می‌شود). اگر بردارِ کوئری در دسترس است، بهترین
     * chunk خودِ همین ورودی از طریق VectorStore انتخاب می‌شود؛ در غیر این صورت (یا اگر هنوز
     * chunk ایندکس‌شده‌ای ندارد) کل content به‌عنوان یک pseudo-chunk برمی‌گردد.
     */
    private function pinnedChunk(KnowledgeEntry $entry, ?array $queryEmbedding): array
    {
        if ($queryEmbedding) {
            $top = $this->vectorStore->search($queryEmbedding, 1, ['knowledge_entry_id_in' => [$entry->id]]);

            if ($top !== []) {
                $chunk = $top[0];

                return [
                    'chunk_id' => $chunk['chunk_id'],
                    'knowledge_entry_id' => $entry->id,
                    'entry_title' => $entry->title,
                    'source' => $entry->source ?: $entry->title,
                    'chunkable_type' => $chunk['chunkable_type'],
                    'chunkable_id' => $chunk['chunkable_id'],
                    'text' => $chunk['text'],
                    'score' => 1.0,
                    'pinned' => true,
                ];
            }
        }

        return $this->pseudoChunk($entry, 1.0, true);
    }

    /**
     * جست‌وجوی معنایی واقعی — روی همه‌ی ورودی‌های available/هم‌زبان/غیر-pin‌شده. هر ردیفِ
     * برگشتی از VectorStore::search به ورودیِ مالکش وصل می‌شود؛ chunkهایی که مالکشان دیگر
     * available نیست (آرشیو/منقضی، اما هنوز ایندکسش پاک نشده) بی‌صدا حذف می‌شوند.
     */
    private function semanticChunkSearch(array $queryEmbedding, string $locale, array $excludedEntryIds, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $entryIds = KnowledgeEntry::query()
            ->available()
            ->where('locale', $locale)
            ->whereNotIn('id', $excludedEntryIds)
            ->pluck('id')
            ->all();

        if ($entryIds === []) {
            return [];
        }

        $raw = $this->vectorStore->search($queryEmbedding, $limit, ['knowledge_entry_id_in' => $entryIds]);

        if ($raw === []) {
            return [];
        }

        $entries = KnowledgeEntry::query()
            ->whereIn('id', collect($raw)->pluck('knowledge_entry_id')->unique()->values())
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->map(function (array $chunk) use ($entries) {
                $entry = $entries->get($chunk['knowledge_entry_id']);

                if (! $entry) {
                    return null;
                }

                return [
                    'chunk_id' => $chunk['chunk_id'],
                    'knowledge_entry_id' => $entry->id,
                    'entry_title' => $entry->title,
                    'source' => $entry->source ?: $entry->title,
                    'chunkable_type' => $chunk['chunkable_type'],
                    'chunkable_id' => $chunk['chunkable_id'],
                    'text' => $chunk['text'],
                    'score' => $chunk['score'],
                    'pinned' => false,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * مسیر fallback — دقیقاً همان منطق کلمه‌ای/رتبه‌بندیِ هوش‌مصنوعیِ قبل از RAG (بدون تغییر:
     * scoreByKeywordOverlap/rankWithAi زیر)، فقط هر ورودیِ انتخاب‌شده به‌شکل یک pseudo-chunk
     * بسته‌بندی می‌شود تا با شکلِ خروجیِ retrieveChunks() یکسان باشد.
     */
    private function keywordFallbackChunks(string $query, string $locale, array $excludedEntryIds, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $candidates = KnowledgeEntry::query()
            ->available()
            ->where('locale', $locale)
            ->whereNotIn('id', $excludedEntryIds)
            ->with('tags')
            ->get();

        if ($candidates->isEmpty()) {
            return [];
        }

        $scored = $this->scoreByKeywordOverlap($query, $candidates)->sortByDesc('score');
        $shortlist = $scored->take(self::MAX_CANDIDATES_FOR_AI_RANKING)->pluck('entry')->values();

        $selected = $this->rankWithAi($query, $shortlist) ?? $shortlist;

        $scoreByEntryId = $scored->pluck('score', 'entry.id');
        $maxScore = max(1, (int) ($scoreByEntryId->max() ?? 1));

        return $selected->take($limit)
            ->map(fn (KnowledgeEntry $entry) => $this->pseudoChunk(
                $entry,
                min(0.99, max(0.0, ($scoreByEntryId->get($entry->id) ?? 0) / $maxScore)),
                false,
            ))
            ->values()
            ->all();
    }

    /**
     * یک ورودی که هنوز chunk واقعیِ ایندکس‌شده ندارد (یا نیازی به آن نیست، مثل مسیر fallback) —
     * کل content به‌عنوان یک chunk واحد نمایش داده می‌شود، بریده‌شده تا در پرامپت/UI منطقی بماند.
     */
    private function pseudoChunk(KnowledgeEntry $entry, float $score, bool $pinned): array
    {
        return [
            'chunk_id' => null,
            'knowledge_entry_id' => $entry->id,
            'entry_title' => $entry->title,
            'source' => $entry->source ?: $entry->title,
            'chunkable_type' => 'KnowledgeEntry',
            'chunkable_id' => $entry->id,
            'text' => Str::limit(strip_tags((string) $entry->content), 1200),
            'score' => $score,
            'pinned' => $pinned,
        ];
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
     * استفاده می‌کند — مسیر fallback وقتی embedding در دسترس نیست. اگر ارائه‌دهنده در دسترس نباشد
     * یا پاسخ قابل‌استفاده نباشد null برمی‌گرداند تا فراخوان به رتبه‌بندی کلمه‌ای برگردد — هرگز
     * تولید محتوا را مسدود نمی‌کند. آرایه‌ی خالیِ واقعی (هوش مصنوعی صراحتاً گفته «هیچ‌کدام مرتبط
     * نیست») از fallback جدا نگه داشته می‌شود.
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
        } catch (Throwable) {
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
