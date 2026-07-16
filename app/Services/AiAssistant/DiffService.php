<?php

namespace App\Services\AiAssistant;

/**
 * دیف واژه‌به‌واژه‌ی متن قدیم/جدید — قبل از هر Apply در AI Assistant نمایش داده می‌شود (حذف قرمز،
 * افزوده سبز). هیچ کتابخانه‌ی دیفی در این پروژه موجود نیست (composer.lock فقط sebastian/diff را در
 * packages-dev دارد، یک وابستگی گذرای phpunit که با --no-dev نصب نمی‌شود) — پس این پیاده‌سازیِ
 * کوچک و بدون وابستگیِ الگوریتم LCS کلاسیک است. ورودی‌ها اینجا کوتاه‌اند (عنوان/توضیحات/یک پاراگراف)
 * پس جدول DP با پیچیدگی O(n·m) کاملا کافی است.
 */
class DiffService
{
    /**
     * @return array<int, array{type: 'same'|'add'|'del', text: string}>
     */
    public function diffWords(?string $old, ?string $new): array
    {
        $oldTokens = $this->tokenize((string) $old);
        $newTokens = $this->tokenize((string) $new);

        $table = $this->lcsTable($oldTokens, $newTokens);

        return $this->mergeAdjacent($this->buildOps($oldTokens, $newTokens, $table));
    }

    /** @return array<int, string> */
    private function tokenize(string $text): array
    {
        $text = trim(strip_tags($text));

        if ($text === '') {
            return [];
        }

        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @return array<int, array<int, int>>
     */
    private function lcsTable(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $table = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $table[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $table[$i - 1][$j - 1] + 1
                    : max($table[$i - 1][$j], $table[$i][$j - 1]);
            }
        }

        return $table;
    }

    /**
     * @param  array<int, string>  $a
     * @param  array<int, string>  $b
     * @param  array<int, array<int, int>>  $table
     * @return array<int, array{type: 'same'|'add'|'del', text: string}>
     */
    private function buildOps(array $a, array $b, array $table): array
    {
        $ops = [];
        $i = count($a);
        $j = count($b);

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $a[$i - 1] === $b[$j - 1]) {
                array_unshift($ops, ['type' => 'same', 'text' => $a[$i - 1]]);
                $i--;
                $j--;
            } elseif ($j > 0 && ($i === 0 || $table[$i][$j - 1] >= $table[$i - 1][$j])) {
                array_unshift($ops, ['type' => 'add', 'text' => $b[$j - 1]]);
                $j--;
            } else {
                array_unshift($ops, ['type' => 'del', 'text' => $a[$i - 1]]);
                $i--;
            }
        }

        return $ops;
    }

    /**
     * @param  array<int, array{type: 'same'|'add'|'del', text: string}>  $ops
     * @return array<int, array{type: 'same'|'add'|'del', text: string}>
     */
    private function mergeAdjacent(array $ops): array
    {
        $merged = [];

        foreach ($ops as $op) {
            $lastIndex = count($merged) - 1;

            if ($lastIndex >= 0 && $merged[$lastIndex]['type'] === $op['type']) {
                $merged[$lastIndex]['text'] .= $op['text'];
            } else {
                $merged[] = $op;
            }
        }

        return $merged;
    }
}
