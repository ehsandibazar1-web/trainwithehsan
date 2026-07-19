<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Media;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OneClickPublishTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleImportService
    {
        return app(ArticleImportService::class);
    }

    // ============ Format detection ============

    public function test_auto_detects_xml_by_the_article_root_tag(): void
    {
        $xml = '<article><language>en</language><title>XML Post</title><content>&lt;p&gt;Body.&lt;/p&gt;</content></article>';

        $analysis = $this->service()->analyze($xml);

        $this->assertSame('xml', $analysis['format']);
        $this->assertSame([], $analysis['errors']);
    }

    public function test_auto_detects_the_custom_bracket_marker_format(): void
    {
        $raw = "[[TITLE]]\nMarker Post\n\n[[CONTENT]]\n<p>Body.</p>\n";

        $analysis = $this->service()->analyze($raw);

        $this->assertSame('custom', $analysis['format']);
        $this->assertSame([], $analysis['errors']);
    }

    public function test_auto_detects_html_fragments(): void
    {
        $raw = "<!--\nlanguage: en\ntitle: HTML Post\n-->\n<p>Body content.</p>";

        $analysis = $this->service()->analyze($raw);

        $this->assertSame('html', $analysis['format']);
        $this->assertSame([], $analysis['errors']);
    }

    // ============ XML parser ============

    public function test_xml_import_maps_seo_og_tags_and_faq(): void
    {
        $xml = <<<'XML'
            <article>
                <language>en</language>
                <title>XML Article</title>
                <content format="html"><![CDATA[<p>Full body.</p>]]></content>
                <excerpt>An excerpt.</excerpt>
                <category>Guides</category>
                <status>published</status>
                <seo>
                    <title>SEO Title Here</title>
                    <meta_description>Meta description here.</meta_description>
                    <keywords><keyword>guard passing</keyword><keyword>bjj basics</keyword></keywords>
                </seo>
                <og>
                    <title>OG Title</title>
                    <description>OG description.</description>
                </og>
                <tags><tag>beginner</tag><tag>technique</tag></tags>
                <faq>
                    <item><question>Q1?</question><answer>A1.</answer></item>
                </faq>
            </article>
            XML;

        $result = $this->service()->import($xml);

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertSame('XML Article', $article->title);
        $this->assertSame('SEO Title Here', $article->seo_title);
        $this->assertSame('Meta description here.', $article->meta_description);
        $this->assertSame('OG Title', $article->og_title);
        $this->assertSame('OG description.', $article->og_description);
        $this->assertSame([['question' => 'Q1?', 'answer' => 'A1.']], $article->faqs);
        $this->assertSame(['beginner', 'technique'], $article->tags->pluck('name')->all());
        $this->assertSame(['guard passing', 'bjj basics'], $article->keywords->pluck('keyword')->all());
    }

    public function test_invalid_xml_is_rejected(): void
    {
        $analysis = $this->service()->analyze('<article><title>Unclosed');

        $this->assertNotEmpty($analysis['errors']);
        $this->assertStringContainsString('Invalid XML', $analysis['errors'][0]);
    }

    public function test_xml_internal_links_create_pending_suggestions(): void
    {
        $target = Article::create([
            'locale' => 'en', 'title' => 'Target Post', 'slug' => 'target-post',
            'body' => 'x', 'status' => 'published', 'published_at' => now(),
        ]);

        $xml = <<<'XML'
            <article>
                <language>en</language>
                <title>Source Post</title>
                <content>&lt;p&gt;Body.&lt;/p&gt;</content>
                <internal_links>
                    <link slug="target-post" anchor="target post" reason="related technique"/>
                </internal_links>
            </article>
            XML;

        $result = $this->service()->import($xml);

        $suggestion = InternalLinkSuggestion::first();
        $this->assertNotNull($suggestion);
        $this->assertSame('pending', $suggestion->status);
        $this->assertSame('ai', $suggestion->origin);
        $this->assertSame($result['article']->id, $suggestion->source_id);
        $this->assertSame($target->id, $suggestion->target_id);
        $this->assertSame('target post', $suggestion->recommended_anchor_text);
    }

    // ============ HTML parser ============

    public function test_html_full_document_extracts_title_meta_and_body(): void
    {
        $html = <<<'HTML'
            <html><head>
                <title>HTML Doc Title</title>
                <meta name="description" content="Doc meta description.">
                <meta property="og:title" content="Doc OG Title">
            </head><body><p>Document body.</p></body></html>
            HTML;

        $result = $this->service()->import($html, format: 'html', context: ['ai_provider' => null], defaults: [
            'language' => 'en', 'publish_status' => 'draft',
        ]);

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertSame('HTML Doc Title', $article->title);
        $this->assertSame('Doc meta description.', $article->meta_description);
        $this->assertSame('Doc OG Title', $article->og_title);
        $this->assertStringContainsString('Document body.', $article->body);
    }

    public function test_html_fragment_uses_leading_comment_block_as_metadata(): void
    {
        $html = "<!--\nlanguage: en\ntitle: Fragment Post\npublish_status: draft\n-->\n<p>Fragment body.</p>";

        $result = $this->service()->import($html, 'html');

        $this->assertSame([], $result['errors']);
        $this->assertSame('Fragment Post', $result['article']->title);
        $this->assertStringContainsString('Fragment body.', $result['article']->body);
    }

    // ============ Custom marker parser ============

    public function test_custom_marker_format_maps_every_supported_section(): void
    {
        $raw = <<<'MARKERS'
            [[LANGUAGE]]
            en

            [[TITLE]]
            Marker Article

            [[SLUG]]
            marker-article

            [[EXCERPT]]
            A marker excerpt.

            [[CONTENT]]
            <p>Marker body.</p>

            [[CATEGORY]]
            Guides

            [[TAGS]]
            alpha, beta

            [[SEO_TITLE]]
            Marker SEO Title

            [[META_DESCRIPTION]]
            Marker meta description.

            [[FAQ]]
            Q: First question?
            A: First answer.

            Q: Second question?
            A: Second answer.

            [[STATUS]]
            published
            MARKERS;

        $result = $this->service()->import($raw);

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertSame('Marker Article', $article->title);
        $this->assertSame('marker-article', $article->slug);
        $this->assertSame('A marker excerpt.', $article->excerpt);
        $this->assertStringContainsString('Marker body.', $article->body);
        $this->assertSame('Guides', $article->category);
        $this->assertSame(['alpha', 'beta'], $article->tags->pluck('name')->all());
        $this->assertSame('Marker SEO Title', $article->seo_title);
        $this->assertSame('Marker meta description.', $article->meta_description);
        $this->assertSame([
            ['question' => 'First question?', 'answer' => 'First answer.'],
            ['question' => 'Second question?', 'answer' => 'Second answer.'],
        ], $article->faqs);
        $this->assertSame('published', $article->status);
    }

    public function test_custom_marker_format_without_markers_is_rejected(): void
    {
        $analysis = $this->service()->analyze('Just plain text with no markers at all, long enough to not look like markdown front matter.', 'custom');

        $this->assertNotEmpty($analysis['errors']);
        $this->assertStringContainsString('No recognizable [[FIELD]] markers', $analysis['errors'][0]);
    }

    // ============ Media Library integration ============

    public function test_downloaded_featured_image_goes_through_media_processor_and_gets_derivatives(): void
    {
        Storage::fake('public');

        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        Http::fake(['cdn.example.com/*' => Http::response($png, 200)]);

        $json = json_encode([
            'language' => 'en', 'title' => 'With Image', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'featured_image' => 'https://cdn.example.com/photo.png', 'image_alt' => 'A descriptive alt text',
        ]);

        $result = $this->service()->import($json);

        $this->assertSame([], $result['errors']);
        $media = Media::first();
        $this->assertNotNull($media->webp_path);
        $this->assertNotNull($media->thumbnail_path);
        $this->assertSame('A descriptive alt text', $media->alt_text);
    }

    public function test_image_alt_is_set_on_an_existing_reused_media_row(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/existing.jpg', 'fake');
        Media::create(['original_name' => 'existing.jpg', 'disk' => 'public', 'disk_path' => 'articles/existing.jpg', 'url' => 'x', 'type' => 'other']);

        $json = json_encode([
            'language' => 'en', 'title' => 'Reused Image', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'featured_image' => 'articles/existing.jpg', 'image_alt' => 'Reused alt text',
        ]);

        $result = $this->service()->import($json);

        $this->assertSame([], $result['errors']);
        $this->assertSame('Reused alt text', Media::first()->alt_text);
    }

    public function test_json_image_alt_is_written_to_the_articles_own_image_alt_column(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/existing.jpg', 'fake');
        Media::create(['original_name' => 'existing.jpg', 'disk' => 'public', 'disk_path' => 'articles/existing.jpg', 'url' => 'x', 'type' => 'other']);

        $json = json_encode([
            'language' => 'en', 'title' => 'Hero Alt From Json', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'featured_image' => 'articles/existing.jpg', 'image_alt' => 'Instructor showing an open-hand de-escalation stance',
        ]);

        $result = $this->service()->import($json);

        // اولویت ۱: image_alt در JSON روی ستونِ articles.image_alt می‌نشیند (نه عنوان مقاله)
        $this->assertSame('Instructor showing an open-hand de-escalation stance', $result['article']->image_alt);
    }

    public function test_hero_alt_falls_back_to_media_library_alt_when_json_omits_it(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/library.jpg', 'fake');
        // این عکس از قبل در کتابخانه‌ی رسانه ALT دارد، ولی JSON آلت نمی‌فرستد
        Media::create(['original_name' => 'library.jpg', 'disk' => 'public', 'disk_path' => 'articles/library.jpg', 'url' => 'x', 'type' => 'other', 'alt_text' => 'Existing library ALT']);

        $json = json_encode([
            'language' => 'en', 'title' => 'No Alt In Json', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'featured_image' => 'articles/library.jpg',
        ]);

        $result = $this->service()->import($json);

        // اولویت ۲: وقتی JSON آلت ندارد، ALTِ کتابخانه‌ی رسانه استفاده می‌شود
        $this->assertSame('Existing library ALT', $result['article']->image_alt);
    }

    public function test_hero_alt_is_left_blank_when_neither_json_nor_media_provides_one(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/bare.jpg', 'fake');
        Media::create(['original_name' => 'bare.jpg', 'disk' => 'public', 'disk_path' => 'articles/bare.jpg', 'url' => 'x', 'type' => 'other']);

        $json = json_encode([
            'language' => 'en', 'title' => 'Title Only Fallback', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'featured_image' => 'articles/bare.jpg',
        ]);

        $result = $this->service()->import($json);

        // اولویت ۳: هیچ آلتی نیست → ستون خالی می‌ماند تا تمپلیت به عنوانِ مقاله برگردد
        $this->assertNull($result['article']->image_alt);
    }

    // نکته‌ی عمدی: import() به‌صورت خودکار GenerateInternalLinkSuggestions (بازتولید مبتنی‌بر
    // قاعده) را صف نمی‌کند — چون SuggestionEngine::generateAndPersist() هر پیشنهاد pending‌ای که
    // در محاسبه‌ی تازه‌ی قاعده‌ای دوباره کشف نشود پاک می‌کند (صرف‌نظر از origin)؛ اگر همین‌جا صف
    // می‌شد، می‌توانست دقیقاً همان پیشنهادهای origin=ai بالا را که تازه ساختیم فوراً حذف کند —
    // این باگ واقعاً حین ساخت این تست کشف و برطرف شد. بازتولید قاعده‌ای همچنان فقط با دکمه‌ی
    // دستیِ موجود در Internal Linking Center اجرا می‌شود.

    // ============ Manual corrections (overrides) ============

    public function test_overrides_win_over_parsed_content_even_when_content_is_non_empty(): void
    {
        $json = json_encode([
            'language' => 'en', 'title' => 'Original Title', 'content' => '<p>x</p>',
            'category' => 'Original Category', 'publish_status' => 'draft',
        ]);

        $result = $this->service()->import($json, 'auto', [], [], [
            'title' => 'Corrected Title',
            'category' => 'Corrected Category',
        ]);

        $this->assertSame([], $result['errors']);
        $this->assertSame('Corrected Title', $result['article']->title);
        $this->assertSame('Corrected Category', $result['article']->category);
    }

    public function test_overrides_can_fix_a_validation_error(): void
    {
        $json = json_encode(['language' => 'xx', 'title' => 'Bad Locale', 'content' => '<p>x</p>', 'publish_status' => 'draft']);

        $failed = $this->service()->analyze($json);
        $this->assertNotEmpty($failed['errors']);

        $fixed = $this->service()->analyze($json, 'auto', [], ['language' => 'en']);
        $this->assertSame([], $fixed['errors']);
    }

    public function test_blank_overrides_do_not_clobber_parsed_content(): void
    {
        $json = json_encode(['language' => 'en', 'title' => 'Keep Me', 'content' => '<p>x</p>', 'publish_status' => 'draft']);

        $result = $this->service()->import($json, 'auto', [], [], ['title' => '']);

        $this->assertSame('Keep Me', $result['article']->title);
    }

    // ============ Twitter Card / CTA / caption / image prompt — detected, not stored ============

    public function test_unsupported_sections_are_detected_and_reported_but_never_block_import(): void
    {
        $json = json_encode([
            'language' => 'en', 'title' => 'Advisory Fields', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'cta' => 'Book a free trial class today!',
            'twitter' => ['title' => 'Tweet title'],
            'image_caption' => 'A caption for the hero image.',
            'featured_image_prompt' => 'A photorealistic image of a BJJ gym.',
        ]);

        $analysis = $this->service()->analyze($json);

        $this->assertSame([], $analysis['errors']);
        $this->assertArrayHasKey('cta', $analysis['mapping']['skipped']);
        $this->assertArrayHasKey('twitter', $analysis['mapping']['skipped']);
        $this->assertArrayHasKey('image_caption', $analysis['mapping']['skipped']);
        $this->assertArrayHasKey('featured_image_prompt', $analysis['mapping']['skipped']);
    }

    // ============ Newsletter Summary — explicitly not supported at all ============

    public function test_newsletter_summary_is_treated_as_a_plain_unknown_field(): void
    {
        $json = json_encode([
            'language' => 'en', 'title' => 'No Newsletter', 'content' => '<p>x</p>', 'publish_status' => 'draft',
            'newsletter_summary' => 'Some summary text.',
        ]);

        $analysis = $this->service()->analyze($json);

        $this->assertSame([], $analysis['errors']);
        $this->assertArrayNotHasKey('newsletter_summary', $analysis['mapping']['mapped']);
        $this->assertArrayNotHasKey('newsletter_summary', $analysis['mapping']['skipped']);
        $this->assertArrayNotHasKey('newsletter_summary', $analysis['mapping']['auto']);
        $this->assertStringContainsString('Unknown field "newsletter_summary"', implode(' ', $analysis['warnings']));
    }
}
