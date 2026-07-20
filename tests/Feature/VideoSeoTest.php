<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Services\Seo\VideoSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Video SEO — C1 (VideoObject برای ویدیوهای درون‌متنیِ مقاله/صفحه) + C2 (Video Sitemap).
 *
 * تشخیص از App\Services\Content\EmbedRenderer (همان providerهای facade) می‌آید — یک منبعِ واحدِ
 * حقیقت که هم schemaِ HTML، هم صفحه‌ی اصلی، و هم video sitemap را تغذیه می‌کند. همه‌چیز افزایشی و
 * backward-compatible است: بدونِ ویدیو، هیچ VideoObject/ورودیِ sitemapی اضافه نمی‌شود.
 */
class VideoSeoTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VideoSchemaService
    {
        return app(VideoSchemaService::class);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'A Standalone Page',
            'slug' => 'standalone-video-page-'.uniqid(),
            'body' => '<p>Body content.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function link(string $href, ?string $text = null): string
    {
        return '<p><a href="'.$href.'">'.($text ?? $href).'</a></p>';
    }

    // ---- C1: VideoObject per article/page video --------------------------------

    public function test_article_with_a_youtube_video_emits_a_valid_videoobject(): void
    {
        $article = $this->makeArticle([
            'body' => '<p>Intro.</p>'.$this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Watch the guard pass'),
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame('VideoObject', $out[0]['@type']);
        $this->assertSame('Watch the guard pass', $out[0]['name']); // نامِ لینک استفاده می‌شود
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
        $this->assertSame('https://img.youtube.com/vi/abcdefghijk/hqdefault.jpg', $out[0]['thumbnailUrl']);
        $this->assertArrayHasKey('uploadDate', $out[0]); // از published_at
        $this->assertArrayNotHasKey('contentUrl', $out[0]);
    }

    public function test_youtube_video_without_link_text_falls_back_to_the_article_title(): void
    {
        $url = 'https://youtu.be/abcdefghijk';
        $article = $this->makeArticle(['title' => 'My Video Article', 'body' => $this->link($url, $url)]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame('My Video Article', $out[0]['name']); // متنِ لینک خودِ URL بود → عنوان مقاله
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
    }

    public function test_article_with_a_vimeo_video_uses_the_featured_image_as_thumbnail(): void
    {
        $article = $this->makeArticle([
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('https://vimeo.com/123456789', 'Seminar highlights'),
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame('https://player.vimeo.com/video/123456789', $out[0]['embedUrl']);
        // ویمئو تامبنیلِ مشتق ندارد → عکسِ شاخصِ مقاله (فایلِ اصلی، نه WebP، تا کوئریِ Media نزنیم)
        $this->assertSame(asset('storage/articles/hero.jpg'), $out[0]['thumbnailUrl']);
    }

    public function test_article_with_a_self_hosted_video_emits_content_url(): void
    {
        $article = $this->makeArticle([
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('/storage/videos/clip.mp4', 'Technique demo'),
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame(asset('storage/articles/hero.jpg'), $out[0]['thumbnailUrl']);
        $this->assertSame(url('/storage/videos/clip.mp4'), $out[0]['contentUrl']);
        $this->assertArrayNotHasKey('embedUrl', $out[0]);
        $this->assertArrayHasKey('uploadDate', $out[0]);
    }

    public function test_vimeo_or_self_hosted_video_without_a_thumbnail_is_skipped(): void
    {
        // نه یوتیوب (تامبنیلِ مشتق) و نه عکسِ شاخص → معتبر نیست، هیچ VideoObjectی
        $vimeo = $this->makeArticle(['image_path' => null, 'body' => $this->link('https://vimeo.com/123456789')]);
        $file = $this->makeArticle(['image_path' => null, 'body' => $this->link('/storage/videos/clip.mp4')]);

        $this->assertCount(0, $this->service()->forArticle($vimeo));
        $this->assertCount(0, $this->service()->forArticle($file));
    }

    public function test_instagram_and_tiktok_and_audio_never_become_videoobjects(): void
    {
        $article = $this->makeArticle([
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('https://www.instagram.com/reel/ABC123def/')
                .$this->link('https://www.tiktok.com/@someone/video/1234567890123456789')
                .$this->link('/storage/audio/podcast.mp3'),
        ]);

        // اینستاگرام/تیک‌تاک خانه‌شان روی پلتفرم است؛ صوت ویدیو نیست — هیچ‌کدام VideoObject نمی‌شوند
        $this->assertCount(0, $this->service()->forArticle($article));
    }

    public function test_multiple_videos_on_one_page_each_get_their_own_videoobject(): void
    {
        $article = $this->makeArticle([
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Part one')
                .'<p>Some text between.</p>'
                .$this->link('https://vimeo.com/123456789', 'Part two'),
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(2, $out);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
        $this->assertSame('https://player.vimeo.com/video/123456789', $out[1]['embedUrl']);
    }

    public function test_the_same_video_repeated_produces_no_duplicate_structured_data(): void
    {
        $link = $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Watch');
        $article = $this->makeArticle(['body' => $link.'<p>...</p>'.$link]);

        $this->assertCount(1, $this->service()->forArticle($article));
    }

    public function test_inline_links_are_not_treated_as_videos(): void
    {
        // فقط پاراگرافِ تنها-لینک embed می‌شود — لینکِ درون‌خطی نه (همان قراردادِ EmbedRenderer)
        $article = $this->makeArticle([
            'body' => '<p>Watch it <a href="https://www.youtube.com/watch?v=abcdefghijk">here</a> now.</p>',
        ]);

        $this->assertCount(0, $this->service()->forArticle($article));
    }

    public function test_page_with_a_youtube_video_emits_a_valid_videoobject(): void
    {
        $page = $this->makePage([
            'body' => $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'How it works'),
        ]);

        $out = $this->service()->forPage($page);

        $this->assertCount(1, $out);
        $this->assertSame('How it works', $out[0]['name']);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
    }

    public function test_article_with_no_video_produces_nothing(): void
    {
        $this->assertCount(0, $this->service()->forArticle($this->makeArticle()));
    }

    // ---- Homepage regression (refactor must not change homepage output) --------

    public function test_homepage_youtube_schema_is_unchanged_after_the_refactor(): void
    {
        $out = $this->service()->forHomepage([
            'video1_caption' => 'How the training works',
            'video1_embed' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
        $this->assertSame('https://img.youtube.com/vi/abcdefghijk/hqdefault.jpg', $out[0]['thumbnailUrl']);
        $this->assertArrayNotHasKey('contentUrl', $out[0]);
    }

    public function test_homepage_embed_video_carries_uploaddate_from_the_fallback_and_a_nonempty_name(): void
    {
        // Google خطای «Missing field uploadDate» را برای ویدیوهای embedِ صفحه‌ی اصلی داد — چون
        // یوتیوب/ویمئو تاریخِ فایل ندارند. حالا fallbackِ سطحِ صفحه (زمانِ آخرین ذخیره‌ی تنظیمات)
        // به همه‌ی VideoObjectها uploadDate می‌دهد، و name/description همیشه غیرخالی‌اند.
        $out = $this->service()->forHomepage([
            'video1_caption' => 'How the training works',
            'video1_embed' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ], [], '2026-07-01T10:00:00+00:00');

        $this->assertCount(1, $out);
        foreach (['name', 'description', 'thumbnailUrl', 'uploadDate'] as $required) {
            $this->assertArrayHasKey($required, $out[0]);
            $this->assertNotSame('', $out[0][$required]);
        }
        $this->assertSame('2026-07-01T10:00:00+00:00', $out[0]['uploadDate']);
    }

    public function test_homepage_member_video_without_a_name_still_gets_a_nonempty_name_and_uploaddate(): void
    {
        $out = $this->service()->forHomepage([], [
            ['name' => '', 'video_embed' => 'https://www.youtube.com/watch?v=abcdefghijk', 'photo' => ''],
        ], '2026-07-01T10:00:00+00:00');

        $this->assertCount(1, $out);
        $this->assertNotSame('', $out[0]['name']);
        $this->assertSame('2026-07-01T10:00:00+00:00', $out[0]['uploadDate']);
    }

    // ---- HTTP rendering: valid JSON-LD, privacy preserved ----------------------

    public function test_article_page_renders_valid_videoobject_json_ld_and_no_eager_iframe(): void
    {
        $article = $this->makeArticle([
            'slug' => 'rendered-video-article',
            'body' => '<p>Intro.</p>'.$this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Watch the guard pass'),
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        // VideoObject در HTML هست و JSON-LDِ آن معتبر است
        $this->assertStringContainsString('"VideoObject"', $html);
        $this->assertStringContainsString('https://www.youtube.com/embed/abcdefghijk', $html);

        $json = $this->extractVideoObject($html);
        $this->assertNotNull($json, 'A parseable VideoObject JSON-LD block must be present');
        $this->assertSame('VideoObject', $json['@type']);
        $this->assertSame('https://schema.org', $json['@context']);
        $this->assertArrayHasKey('name', $json);
        $this->assertArrayHasKey('thumbnailUrl', $json);
        $this->assertArrayHasKey('uploadDate', $json);

        // حریمِ خصوصی حفظ می‌شود — هیچ <iframe> ای پیش از کلیکِ کاربر در HTMLِ سرور نیست (فقط facade)
        $this->assertStringNotContainsString('<iframe', $html);
    }

    public function test_article_without_video_still_renders_and_adds_no_videoobject(): void
    {
        $article = $this->makeArticle(['slug' => 'plain-article']);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('"Article"', $html); // schemaِ مقاله دست‌نخورده
        $this->assertStringNotContainsString('"VideoObject"', $html); // چیزی اضافه نشده
    }

    // ---- C2: Video sitemap ------------------------------------------------------

    public function test_sitemap_includes_video_entries_and_is_well_formed_xml(): void
    {
        $article = $this->makeArticle([
            'slug' => 'sitemap-video-article',
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Watch the guard pass'),
        ]);

        $xml = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->getContent();

        // namespace + بلاکِ ویدیو
        $this->assertStringContainsString('xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"', $xml);
        $this->assertStringContainsString('<video:video>', $xml);
        $this->assertStringContainsString('<video:thumbnail_loc>https://img.youtube.com/vi/abcdefghijk/hqdefault.jpg</video:thumbnail_loc>', $xml);
        $this->assertStringContainsString('<video:title>Watch the guard pass</video:title>', $xml);
        $this->assertStringContainsString('<video:player_loc>https://www.youtube.com/embed/abcdefghijk</video:player_loc>', $xml);
        $this->assertStringContainsString('<video:publication_date>', $xml);

        // XMLِ معتبر — با namespaceها بارگذاری می‌شود و ورودیِ ویدیو دقیقاً یکی است
        $doc = simplexml_load_string($xml);
        $this->assertNotFalse($doc, 'Sitemap must be well-formed XML');
        $doc->registerXPathNamespace('video', 'http://www.google.com/schemas/sitemap-video/1.1');
        $this->assertCount(1, $doc->xpath('//video:video'));
    }

    public function test_sitemap_without_any_video_content_has_no_video_entries(): void
    {
        $this->makeArticle(['slug' => 'no-video-article']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        // namespace اعلام می‌شود (بی‌ضرر) ولی هیچ <video:video> ای نیست
        $this->assertStringNotContainsString('<video:video>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    // ---- H1: og:video + Twitter Player Card ------------------------------------

    public function test_primary_social_video_maps_an_embed_to_player_card_fields(): void
    {
        $sv = $this->service()->primarySocialVideo([
            ['@type' => 'VideoObject', 'name' => 'Clip', 'description' => 'A clip', 'thumbnailUrl' => 'https://img/x.jpg', 'embedUrl' => 'https://www.youtube.com/embed/abcdefghijk'],
        ]);

        $this->assertNotNull($sv);
        $this->assertTrue($sv['is_embed']);
        $this->assertTrue($sv['secure']);
        $this->assertSame('text/html', $sv['type']);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $sv['url']);
    }

    public function test_primary_social_video_maps_self_hosted_to_a_file_without_player_card(): void
    {
        $sv = $this->service()->primarySocialVideo([
            ['@type' => 'VideoObject', 'name' => 'Demo', 'description' => 'x', 'thumbnailUrl' => 'https://img/x.jpg', 'contentUrl' => 'http://localhost/storage/videos/clip.mp4'],
        ]);

        $this->assertNotNull($sv);
        $this->assertFalse($sv['is_embed']); // فایل → og:video بله، Player Card نه
        $this->assertFalse($sv['secure']);   // http → og:video:secure_url حذف می‌شود
        $this->assertSame('video/mp4', $sv['type']);
    }

    public function test_primary_social_video_is_null_when_there_are_no_videos(): void
    {
        $this->assertNull($this->service()->primarySocialVideo([]));
    }

    public function test_article_youtube_video_renders_og_video_and_twitter_player(): void
    {
        $article = $this->makeArticle([
            'slug' => 'og-youtube-article',
            'body' => $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'Watch this'),
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:video" content="https://www.youtube.com/embed/abcdefghijk">', $html);
        $this->assertStringContainsString('<meta property="og:video:secure_url" content="https://www.youtube.com/embed/abcdefghijk">', $html);
        $this->assertStringContainsString('<meta property="og:video:type" content="text/html">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="player">', $html);
        $this->assertStringContainsString('<meta name="twitter:player" content="https://www.youtube.com/embed/abcdefghijk">', $html);
        $this->assertStringContainsString('<meta name="twitter:player:width" content="1280">', $html);
    }

    public function test_article_self_hosted_video_renders_og_video_but_no_player_card(): void
    {
        $article = $this->makeArticle([
            'slug' => 'og-selfhosted-article',
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('/storage/videos/demo.mp4', 'Technique demo'),
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:video" content="'.url('/storage/videos/demo.mp4').'">', $html);
        $this->assertStringContainsString('<meta property="og:video:type" content="video/mp4">', $html);
        // فایلِ خودمیزبان iframe ندارد → هیچ Twitter Player Card ای نباید باشد
        $this->assertStringNotContainsString('name="twitter:card" content="player"', $html);
    }

    public function test_only_the_primary_video_drives_the_social_tags(): void
    {
        $article = $this->makeArticle([
            'slug' => 'og-multi-video-article',
            'image_path' => 'articles/hero.jpg',
            'body' => $this->link('https://www.youtube.com/watch?v=abcdefghijk', 'First')
                .$this->link('https://vimeo.com/123456789', 'Second'),
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        // فقط یک og:video و یک Player Card — برای ویدیوی اول (primary)
        $this->assertSame(1, substr_count($html, '<meta property="og:video" content='));
        $this->assertSame(1, substr_count($html, 'name="twitter:card" content="player"'));
        $this->assertStringContainsString('content="https://www.youtube.com/embed/abcdefghijk">', $html);
    }

    public function test_article_without_video_renders_no_og_video_tags(): void
    {
        $article = $this->makeArticle(['slug' => 'og-plain-article']);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringNotContainsString('property="og:video"', $html);
        $this->assertStringNotContainsString('name="twitter:card" content="player"', $html);
    }

    public function test_page_with_a_video_renders_og_video_tags(): void
    {
        $page = $this->makePage([
            'slug' => 'og-video-page',
            'image_path' => 'pages/hero.jpg', // ویمئو تامبنیلِ مشتق ندارد → عکسِ شاخص لازم است وگرنه ویدیو رد می‌شود
            'body' => $this->link('https://vimeo.com/123456789', 'Walkthrough'),
        ]);

        $html = $this->get($page->path())->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:video" content="https://player.vimeo.com/video/123456789">', $html);
        $this->assertStringContainsString('<meta name="twitter:player" content="https://player.vimeo.com/video/123456789">', $html);
    }

    /**
     * اولین بلاکِ JSON-LDِ VideoObject را از HTML بیرون می‌کشد و decode می‌کند.
     *
     * @return array<string, mixed>|null
     */
    private function extractVideoObject(string $html): ?array
    {
        if (! preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m)) {
            return null;
        }

        foreach ($m[1] as $block) {
            $data = json_decode(trim($block), true);
            if (is_array($data) && ($data['@type'] ?? null) === 'VideoObject') {
                return $data;
            }
        }

        return null;
    }
}
