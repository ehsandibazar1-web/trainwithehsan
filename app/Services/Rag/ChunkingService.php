<?php

namespace App\Services\Rag;

/**
 * تکه‌تکه‌کردنِ متنِ استخراج‌شده به chunkهای هم‌پوشان — بدون هیچ کتابخانه‌ی تازه‌ای (هم‌روحِ
 * DiffService/SuggestionEngine در این پروژه: پردازش متن دست‌ساز به‌جای افزودن یک وابستگی برای
 * کاری که چند خط کد ساده انجامش می‌دهد). یک پنجره‌ی لغزانِ ساده روی کلماتِ متن — هر chunk حدود
 * TARGET_WORDS کلمه است، و هر chunk تازه با OVERLAP_WORDS کلمه از انتهای chunk قبلی شروع می‌شود
 * تا زمینه‌ی معنایی در مرز دو chunk (حتی وسط یک پاراگراف) از دست نرود.
 */
class ChunkingService
{
    private const TARGET_WORDS = 220;

    private const OVERLAP_WORDS = 40;

    private const MIN_CHUNK_WORDS = 20;

    /**
     * @return string[] فهرست متنِ هر chunk، به ترتیب
     */
    public function chunk(string $text): array
    {
        $words = $this->toWords(trim($text));

        if ($words === []) {
            return [];
        }

        if (count($words) <= self::TARGET_WORDS) {
            return [implode(' ', $words)];
        }

        $chunks = [];
        $start = 0;
        $total = count($words);

        while ($start < $total) {
            $end = min($start + self::TARGET_WORDS, $total);
            $chunks[] = implode(' ', array_slice($words, $start, $end - $start));

            if ($end >= $total) {
                break;
            }

            $start = max(0, $end - self::OVERLAP_WORDS);
        }

        // اگر آخرین chunk خیلی کوچک است (مثلا فقط چند کلمه‌ی باقی‌مانده از overlap)، آن را در
        // chunk قبلی ادغام کن تا یک chunk بی‌فایده و تقریبا تکراری در ایندکس نماند
        if (count($chunks) > 1 && $this->wordCount((string) end($chunks)) < self::MIN_CHUNK_WORDS) {
            $last = array_pop($chunks);
            $previous = array_pop($chunks);
            $chunks[] = trim($previous.' '.$last);
        }

        return array_values($chunks);
    }

    /**
     * @return string[]
     */
    private function toWords(string $text): array
    {
        if ($text === '') {
            return [];
        }

        return preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    private function wordCount(string $text): int
    {
        return count($this->toWords($text));
    }
}
