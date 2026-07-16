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
     * @return \DOMNodeList<\DOMElement>
     */
    private function queryElements(string $html, string $tag): \DOMNodeList
    {
        $dom = new \DOMDocument;

        $previous = libxml_use_internal_errors(true);
        // بدنه‌ی مقاله فقط یک قطعه HTML است، نه سند کامل — NOIMPLIED مانع اضافه‌شدن <html><body> می‌شود
        // و پیشوند encoding مانع درهم‌ریختن کاراکترهای فارسی/ترکی هنگام پارس می‌شود
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom->getElementsByTagName($tag);
    }
}
