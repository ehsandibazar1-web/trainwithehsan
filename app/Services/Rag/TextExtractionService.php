<?php

namespace App\Services\Rag;

use App\Models\KnowledgeEntryAttachment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser as PdfParser;

/**
 * استخراج متن خام از هر شکل ورودیِ درخواست‌شده برای RAG — PDF/DOCX/HTML/Markdown/TXT، به‌علاوه‌ی
 * واکشی یک صفحه‌ی وب (URL). هیچ‌کدام از خروجی‌ها اینجا chunk/embed نمی‌شوند — این فقط لایه‌ی
 * «متن تمیز» است، App\Services\Rag\ChunkingService و ProviderManager::embed() جداگانه صدا زده
 * می‌شوند (App\Services\Rag\IndexingService).
 */
class TextExtractionService
{
    // چون سند xmlns:w را اعلام می‌کند، DOMDocument::getElementsByTagName('w:p') چیزی پیدا نمی‌کند
    // (آن متد روی نام کامل بدون در نظر گرفتن namespace مقایسه می‌کند) — extractFromDocx() باید از
    // getElementsByTagNameNS با این URI رسمی wordprocessingml استفاده کند.
    private const WORDPROCESSINGML_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * استخراج برای یک پیوستِ خاص — بر اساس source_type (فایل روی دیسک یا URL) و سپس mime/پسوند.
     */
    public function extractForAttachment(KnowledgeEntryAttachment $attachment): string
    {
        if ($attachment->isUrlSource()) {
            return $this->extractFromUrl((string) $attachment->source_url);
        }

        $absolutePath = Storage::disk('public')->path($attachment->disk_path);

        if (! is_file($absolutePath)) {
            throw new \RuntimeException("Attachment file not found on disk: {$attachment->disk_path}");
        }

        $mime = (string) ($attachment->mime_type ?? '');
        $extension = strtolower(pathinfo($attachment->original_filename, PATHINFO_EXTENSION));

        return match (true) {
            str_contains($mime, 'pdf') || $extension === 'pdf' => $this->extractFromPdf($absolutePath),
            str_contains($mime, 'wordprocessingml') || $extension === 'docx' => $this->extractFromDocx($absolutePath),
            $extension === 'doc' => throw new \RuntimeException('Legacy .doc format is not supported — please re-save as .docx or .pdf.'),
            str_contains($mime, 'html') || in_array($extension, ['html', 'htm'], true) => $this->extractFromHtml((string) file_get_contents($absolutePath)),
            in_array($extension, ['md', 'markdown'], true) => $this->extractFromMarkdown((string) file_get_contents($absolutePath)),
            default => $this->extractFromText((string) file_get_contents($absolutePath)),
        };
    }

    public function extractFromPdf(string $absolutePath): string
    {
        try {
            $pdf = (new PdfParser)->parseFile($absolutePath);

            return trim($pdf->getText());
        } catch (\Throwable $e) {
            throw new \RuntimeException('Could not extract text from PDF: '.$e->getMessage(), previous: $e);
        }
    }

    // DOCX یک فایل zip حاوی word/document.xml است — بدون هیچ کتابخانه‌ی تازه‌ای (ZipArchive و
    // DOMDocument هر دو از قبل در PHP هستند)؛ فقط متنِ داخل <w:t> به‌ازای هر پاراگراف <w:p>
    // برداشته می‌شود، بدون فرمت‌بندی/جدول/تصویر — برای embedding کافی است.
    public function extractFromDocx(string $absolutePath): string
    {
        $zip = new \ZipArchive;

        if ($zip->open($absolutePath) !== true) {
            throw new \RuntimeException('Could not open DOCX file as a zip archive.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new \RuntimeException('This file does not contain word/document.xml — not a valid Word (.docx) document.');
        }

        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $paragraphs = [];

        foreach ($dom->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 'p') as $p) {
            $runs = [];
            foreach ($p->getElementsByTagNameNS(self::WORDPROCESSINGML_NS, 't') as $t) {
                $runs[] = $t->textContent;
            }
            $text = trim(implode('', $runs));

            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        return implode("\n\n", $paragraphs);
    }

    // بر خلاف App\Services\Seo\HtmlContentScanner (که فقط <p> بدنه‌ی مقاله را می‌خواند)، اینجا باید
    // هر سند HTML دلخواه (فایل آپلودشده یا صفحه‌ی وبِ واکشی‌شده) را پوشش دهد — عناوین/فهرست/جدول
    // هم بخشی از متن قابل‌بازیابی‌اند، نه فقط پاراگراف
    public function extractFromHtml(string $html): string
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (['script', 'style', 'noscript'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        $xpath = new \DOMXPath($dom);
        $blocks = $xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //li | //blockquote | //td | //caption');

        $paragraphs = [];
        foreach ($blocks ?: [] as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent) ?? '');
            if ($text !== '') {
                $paragraphs[] = $text;
            }
        }

        if ($paragraphs === []) {
            // سند/قطعه‌ای بدون تگ‌های بلوکِ شناخته‌شده (مثلا یک fragment ساده) — کل متن را برگردان
            return trim(preg_replace('/\s+/u', ' ', $dom->textContent) ?? '');
        }

        return implode("\n\n", $paragraphs);
    }

    // پاک‌سازیِ سبک نشانه‌گذاری مارک‌داون — کامل نیست (یک تجزیه‌گر مارک‌داون واقعی برای این کار
    // زیاده‌روی است)، فقط رایج‌ترین نشانه‌ها را حذف می‌کند تا متنِ رسیده به embedding تمیزتر باشد
    public function extractFromMarkdown(string $markdown): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $markdown);
        $text = preg_replace('/```[a-zA-Z0-9]*\n?/', '', $text) ?? $text;
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s*/m', '', $text) ?? $text;
        $text = preg_replace('/(\*\*|__)(.*?)\1/', '$2', $text) ?? $text;
        $text = preg_replace('/(?<!\w)(\*|_)(.*?)\1(?!\w)/', '$2', $text) ?? $text;
        $text = preg_replace('/^>\s?/m', '', $text) ?? $text;
        $text = preg_replace('/^[-*+]\s+/m', '', $text) ?? $text;
        $text = preg_replace('/^\d+\.\s+/m', '', $text) ?? $text;

        return trim($text);
    }

    public function extractFromText(string $text): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $text));
    }

    // صفحه‌ی وب — همان الگوی SeoAuditService::checkUrls برای تماس HTTP، بعلاوه‌ی یک مسیر جدا برای
    // وقتی خودِ URL یک PDF باشد (مثلا لینک مستقیم به یک بروشور)
    public function extractFromUrl(string $url): string
    {
        try {
            $response = Http::timeout(20)->connectTimeout(10)->get($url);
        } catch (\Throwable $e) {
            throw new \RuntimeException("Could not fetch {$url}: ".$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new \RuntimeException("Could not fetch {$url}: HTTP {$response->status()}");
        }

        $contentType = (string) $response->header('Content-Type');

        if (str_contains($contentType, 'application/pdf')) {
            $tmp = tempnam(sys_get_temp_dir(), 'rag_pdf_');

            try {
                file_put_contents($tmp, $response->body());

                return $this->extractFromPdf($tmp);
            } finally {
                @unlink($tmp);
            }
        }

        return $this->extractFromHtml($response->body());
    }
}
