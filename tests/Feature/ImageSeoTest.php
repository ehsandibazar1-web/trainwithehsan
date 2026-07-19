<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Page;
use App\Services\Media\MediaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Image SEO — اسمِ فایلِ توصیفی، ALTِ چندزبانه‌ی عکسِ شاخص، هیرو به‌صورتِ <img> واقعی،
 * og:image و image در schemaی مقاله/صفحه.
 */
class ImageSeoTest extends TestCase
{
    use RefreshDatabase;

    private function processor(): MediaProcessor
    {
        return app(MediaProcessor::class);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en', 'title' => 'Guard Passing', 'slug' => 'guard-'.uniqid(),
            'excerpt' => 'A genuinely useful standalone summary sentence for this article.',
            'body' => '<p>Body.</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
        ], $overrides));
    }

    // ---- descriptive filenames -------------------------------------------------

    public function test_upload_gets_a_descriptive_slug_filename_from_the_original_name(): void
    {
        Storage::fake('public');
        $media = $this->processor()->store(UploadedFile::fake()->image('Muay Thai Training.png', 400, 300), 'articles');

        $this->assertSame('articles/muay-thai-training.png', $media->disk_path);
    }

    public function test_filename_collision_gets_a_numeric_suffix(): void
    {
        Storage::fake('public');
        $a = $this->processor()->store(UploadedFile::fake()->image('hero.png', 400, 300), 'articles');
        $b = $this->processor()->store(UploadedFile::fake()->image('hero.png', 400, 300), 'articles');

        $this->assertSame('articles/hero.png', $a->disk_path);
        $this->assertSame('articles/hero-2.png', $b->disk_path);
    }

    public function test_non_latin_filename_falls_back_to_a_non_empty_name(): void
    {
        Storage::fake('public');
        $media = $this->processor()->store(UploadedFile::fake()->image('عکس.png', 400, 300), 'articles');

        // slugِ خالی → ULID؛ نه اسمِ خالی، نه فاصله، پسوند حفظ می‌شود
        $this->assertStringStartsWith('articles/', $media->disk_path);
        $this->assertStringEndsWith('.png', $media->disk_path);
        $this->assertStringNotContainsString(' ', $media->disk_path);
        $this->assertGreaterThan(strlen('articles/.png'), strlen($media->disk_path));
    }

    // ---- hero <img alt> + per-language ALT + og:image + schema image -----------

    public function test_blog_post_hero_renders_a_real_img_with_the_articles_own_alt(): void
    {
        $article = $this->makeArticle([
            'slug' => 'hero-en', 'image_path' => 'articles/muay.png', 'image_alt' => 'Muay Thai training in Istanbul',
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('<img src="'.asset('storage/articles/muay.png').'" alt="Muay Thai training in Istanbul"', $html);
        // og:image (original file) present
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('storage/articles/muay.png').'">', $html);
        // og:image:alt carries the featured image's own ALT
        $this->assertStringContainsString('<meta property="og:image:alt" content="Muay Thai training in Istanbul">', $html);
        // Article JSON-LD carries the image as an ImageObject with a caption = ALT
        $this->assertStringContainsString('"image": {"@type": "ImageObject"', $html);
        $this->assertStringContainsString('"caption": "Muay Thai training in Istanbul"', $html);
        $this->assertStringContainsString(asset('storage/articles/muay.png'), $html);
    }

    public function test_og_image_alt_and_schema_caption_fall_back_to_the_title(): void
    {
        $article = $this->makeArticle([
            'slug' => 'ogalt-fallback', 'title' => 'The Fallback Headline', 'image_path' => 'articles/x.png', 'image_alt' => null,
        ]);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:image:alt" content="The Fallback Headline">', $html);
        $this->assertStringContainsString('"caption": "The Fallback Headline"', $html);
    }

    public function test_turkish_article_hero_uses_its_own_localized_alt(): void
    {
        $en = $this->makeArticle(['slug' => 'pair-en', 'image_path' => 'articles/muay.png', 'image_alt' => 'Muay Thai training in Istanbul']);
        $tr = $this->makeArticle([
            'slug' => 'pair-tr', 'locale' => 'tr', 'translation_of' => $en->id, 'title' => 'Muay Thai TR',
            'image_path' => 'articles/muay.png', 'image_alt' => "İstanbul'da Muay Thai antrenmanı",
        ]);

        $html = $this->get($tr->path())->assertOk()->getContent();

        $this->assertStringContainsString('alt="İstanbul&#039;da Muay Thai antrenmanı"', $html);
        $this->assertStringNotContainsString('alt="Muay Thai training in Istanbul"', $html);
    }

    public function test_hero_alt_falls_back_to_the_title_when_blank(): void
    {
        $article = $this->makeArticle(['slug' => 'noalt', 'title' => 'Fallback Title', 'image_path' => 'articles/x.png', 'image_alt' => null]);

        $html = $this->get($article->path())->assertOk()->getContent();

        $this->assertStringContainsString('alt="Fallback Title"', $html);
    }

    public function test_article_without_an_image_renders_no_hero_img_but_falls_back_to_the_brand_og_image(): void
    {
        $article = $this->makeArticle(['slug' => 'noimg', 'image_path' => null]);

        $html = $this->get($article->path())->assertOk()->getContent();

        // بدونِ عکسِ شاخص، تگِ هیرو رندر نمی‌شود...
        $this->assertStringNotContainsString('class="post-hero-image"><img', str_replace(["\n", ' '], '', $html));
        // ...ولی og:image به لوگوی برند به‌عنوان پیش‌فرضِ سایت‌گستر برمی‌گردد (هر اشتراک‌گذاری پیش‌نمایشِ تصویری دارد)
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('storage/homepage/logo.header.png').'">', $html);
    }

    public function test_home_page_has_a_default_brand_og_image_and_twitter_card(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // صفحه‌ی اصلی عکسِ اختصاصی ندارد → لوگوی برند به‌عنوان og:image و twitter:image پیش‌فرض
        $this->assertStringContainsString('<meta property="og:image" content="'.asset('storage/homepage/logo.header.png').'">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta name="twitter:image" content="'.asset('storage/homepage/logo.header.png').'">', $html);
        $this->assertStringContainsString('<meta property="og:locale" content="en_US">', $html);
    }

    public function test_page_hero_renders_a_real_img_with_its_own_alt(): void
    {
        $page = Page::create([
            'locale' => 'en', 'title' => 'Standalone', 'slug' => 'standalone-img-'.uniqid(),
            'body' => '<p>'.str_repeat('Long body text. ', 10).'</p>', 'status' => 'published', 'published_at' => now()->subDay(),
            'image_path' => 'pages/hero.png', 'image_alt' => 'A descriptive page hero',
        ]);

        $html = $this->get($page->path())->assertOk()->getContent();

        $this->assertStringContainsString('alt="A descriptive page hero"', $html);
        $this->assertStringContainsString('"image":', $html);
    }
}
