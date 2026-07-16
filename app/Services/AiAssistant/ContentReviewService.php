<?php

namespace App\Services\AiAssistant;

use App\Models\Article;
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
}
