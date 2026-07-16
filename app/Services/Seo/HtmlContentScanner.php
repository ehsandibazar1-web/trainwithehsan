<?php

namespace App\Services\Seo;

/**
 * ابزار مشترک برای خواندن لینک‌ها و تصاویر از HTML خام (خروجی RichEditor مقاله/صفحه) —
 * هم برای بررسی ALT گمشده، هم برای بررسی لینک‌های داخلی/خارجی خراب استفاده می‌شود.
 */
class HtmlContentScanner
{
    /**
     * @return array<int, array{href: string, text: string}>
     */
    public function links(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $links = [];
        foreach ($this->queryElements($html, 'a') as $node) {
            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                continue;
            }

            $links[] = ['href' => $href, 'text' => trim($node->textContent)];
        }

        return $links;
    }

    /**
     * @return array<int, array{src: string, alt: string}>
     */
    public function images(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $images = [];
        foreach ($this->queryElements($html, 'img') as $node) {
            $src = trim($node->getAttribute('src'));
            if ($src === '') {
                continue;
            }

            $images[] = ['src' => $src, 'alt' => trim($node->getAttribute('alt'))];
        }

        return $images;
    }

    /**
     * @return array<int, array{level: int, text: string}>
     */
    public function headings(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $xpath = new \DOMXPath($this->loadDocument($html));
        $headings = [];

        foreach ($xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $node) {
            $headings[] = ['level' => (int) substr($node->nodeName, 1), 'text' => trim($node->textContent)];
        }

        return $headings;
    }

    /**
     * @return array<int, array{text: string, word_count: int}>
     */
    public function paragraphs(?string $html): array
    {
        if (blank($html)) {
            return [];
        }

        $paragraphs = [];
        foreach ($this->queryElements($html, 'p') as $node) {
            $text = trim($node->textContent);
            if ($text === '') {
                continue;
            }

            // شمارش کلمات با split روی فاصله (نه str_word_count) چون آن تابع کاراکترهای
            // ویژه‌ی ترکی (ç ğ ı ö ş ü) را کلمه‌ی جدا حساب نمی‌کند و شمارش را به‌هم می‌ریزد
            $wordCount = count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY));

            $paragraphs[] = ['text' => $text, 'word_count' => $wordCount];
        }

        return $paragraphs;
    }

    /**
     * @return \DOMNodeList<\DOMElement>
     */
    private function queryElements(string $html, string $tag): \DOMNodeList
    {
        return $this->loadDocument($html)->getElementsByTagName($tag);
    }

    private function loadDocument(string $html): \DOMDocument
    {
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // بدنه‌ی مقاله فقط یک قطعه HTML است، نه سند کامل — NOIMPLIED مانع اضافه‌شدن <html><body> می‌شود
        // و پیشوند encoding مانع درهم‌ریختن کاراکترهای فارسی/ترکی هنگام پارس می‌شود
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }
}
