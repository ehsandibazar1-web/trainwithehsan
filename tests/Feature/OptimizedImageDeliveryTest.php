<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Media\MediaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Public templates prefer the DAM-generated WebP derivative for a featured image
 * (`Article`/`Page::getOptimizedImageUrlAttribute()`, backed by the pre-existing
 * `Media::forRecord()`/`webp_url`) and fall back to the raw original for any image that
 * predates the DAM or has no Media row — the original upload path/byte content is never
 * touched (see Image Optimization Rules in CLAUDE.md). `og:image` deliberately keeps
 * serving the original file, never WebP, for social-crawler compatibility.
 */
class OptimizedImageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function fakeImage(int $width = 1600, int $height = 900): UploadedFile
    {
        return UploadedFile::fake()->image('hero.jpg', $width, $height);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Body.</p>',
            'author_name' => 'Ehsan',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>Body.</p>',
            'status' => 'published',
            'published_at' => now()->subDay(),
        ], $overrides));
    }

    // ============ Model accessor ============

    public function test_optimized_image_url_returns_the_webp_derivative_when_a_media_row_exists(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'articles', 'public');
        $article = $this->makeArticle(['image_path' => $media->disk_path]);

        $this->assertNotNull($article->optimized_image_url);
        $this->assertStringEndsWith('.webp', $article->optimized_image_url);
    }

    public function test_optimized_image_url_is_null_when_no_matching_media_row_exists(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/legacy-not-in-dam.jpg']);

        $this->assertNull($article->optimized_image_url);
    }

    public function test_optimized_image_url_is_null_when_the_record_has_no_image(): void
    {
        $article = $this->makeArticle(['image_path' => null]);

        $this->assertNull($article->optimized_image_url);
    }

    public function test_page_optimized_image_url_mirrors_article_behavior(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'pages', 'public');
        $page = $this->makePage(['image_path' => $media->disk_path]);

        $this->assertNotNull($page->optimized_image_url);
        $this->assertStringEndsWith('.webp', $page->optimized_image_url);
    }

    // ============ Public templates ============

    public function test_home_page_serves_the_width_capped_webp_variant_for_article_cards(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'articles', 'public');
        $this->makeArticle(['image_path' => $media->disk_path]);

        // کارتِ ~۲۷۰px سقفِ ۸۰۰px می‌گیرد — نه WebPِ فول‌سایز (یافته‌ی GTmetrix: اتلافِ ۳۶۵KiB)
        $this->get('/')->assertOk()->assertSee($media->responsive_urls[800], false);
    }

    public function test_blog_index_serves_the_width_capped_webp_variant(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'articles', 'public');
        $this->makeArticle(['image_path' => $media->disk_path]);

        $this->get('/blog')->assertOk()->assertSee($media->responsive_urls[800], false);
    }

    public function test_blog_post_prefers_the_webp_url_for_the_hero_image(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'articles', 'public');
        $article = $this->makeArticle(['image_path' => $media->disk_path]);

        $this->get('/blog/'.$article->slug)->assertOk()->assertSee($media->webp_url, false);
    }

    public function test_blog_post_falls_back_to_the_original_path_when_no_media_row_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/legacy.jpg', 'fake-bytes');
        $article = $this->makeArticle(['image_path' => 'articles/legacy.jpg']);

        $this->get('/blog/'.$article->slug)
            ->assertOk()
            ->assertSee(asset('storage/articles/legacy.jpg'), false);
    }

    public function test_standalone_page_prefers_the_webp_url_for_the_hero_but_og_image_stays_the_original(): void
    {
        Storage::fake('public');
        $media = app(MediaProcessor::class)->store($this->fakeImage(), 'pages', 'public');
        $page = $this->makePage(['image_path' => $media->disk_path]);

        $response = $this->get('/'.$page->slug)->assertOk();
        $response->assertSee($media->webp_url, false);
        // og:image must keep pointing at the original file — WebP support in social-media
        // link-preview crawlers is unreliable, this is a deliberate, permanent decision
        $response->assertSee('og:image" content="'.asset('storage/'.$page->image_path).'"', false);
    }

    // ---- Media::optimizedUrl() — تصاویرِ SiteSetting-محورِ صفحه‌ی اصلی (هیرو/درباره/...) ----

    public function test_media_optimized_url_prefers_webp_and_falls_back_to_the_original(): void
    {
        Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'media/library/hero-x.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'media/library/hero-x.webp',
        ]);

        $this->assertStringEndsWith('media/library/hero-x.webp', Media::optimizedUrl('media/library/hero-x.jpg'));
        // بدونِ ردیفِ Media → فایلِ اصلی (سازگاریِ عقب‌رو، مثلِ optimized_image_url)
        $this->assertSame(asset('storage/homepage/no-row.png'), Media::optimizedUrl('homepage/no-row.png'));
        $this->assertNull(Media::optimizedUrl(null));
        $this->assertNull(Media::optimizedUrl(''));
    }

    public function test_optimized_url_with_max_width_picks_the_largest_variant_that_fits(): void
    {
        Media::create([
            'original_name' => 'card.jpg', 'disk' => 'public', 'disk_path' => 'articles/card-v.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'articles/card-v.webp',
            'responsive_paths' => [480 => 'articles/card-v-480.webp', 800 => 'articles/card-v-800.webp', 1200 => 'articles/card-v-1200.webp'],
        ]);
        Media::create([
            'original_name' => 'small.jpg', 'disk' => 'public', 'disk_path' => 'articles/small-v.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'articles/small-v.webp',
        ]);

        // بزرگ‌ترین واریانتِ ≤ سقف — نه کوچک‌ترین، نه بزرگ‌تر از سقف
        $this->assertStringEndsWith('card-v-800.webp', Media::optimizedUrl('articles/card-v.jpg', 800));
        $this->assertStringEndsWith('card-v-480.webp', Media::optimizedUrl('articles/card-v.jpg', 480));
        $this->assertStringEndsWith('card-v-1200.webp', Media::optimizedUrl('articles/card-v.jpg', 5000));
        // بدونِ سقف → WebPِ کامل (رفتارِ قبلی دست‌نخورده)
        $this->assertStringEndsWith('card-v.webp', Media::optimizedUrl('articles/card-v.jpg'));
        // بدونِ واریانت → WebPِ کامل؛ بدونِ ردیفِ Media → فایلِ اصلی
        $this->assertStringEndsWith('small-v.webp', Media::optimizedUrl('articles/small-v.jpg', 480));
        $this->assertSame(asset('storage/no/row-v.jpg'), Media::optimizedUrl('no/row-v.jpg', 800));
    }

    public function test_srcset_for_builds_a_width_descriptor_list_and_is_null_without_variants(): void
    {
        Media::create([
            'original_name' => 'insta.jpg', 'disk' => 'public', 'disk_path' => 'media/library/insta-z.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'media/library/insta-z.webp',
            'responsive_paths' => [800 => 'media/library/insta-z-800.webp', 480 => 'media/library/insta-z-480.webp'],
        ]);
        Media::create([
            'original_name' => 'small.jpg', 'disk' => 'public', 'disk_path' => 'media/library/small.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'media/library/small.webp',
        ]);

        // مرتب از کوچک به بزرگ (responsive_urls خودش sortKeys می‌کند)
        $srcset = Media::srcsetFor('media/library/insta-z.jpg');
        $this->assertStringContainsString('insta-z-480.webp 480w', $srcset);
        $this->assertStringContainsString('insta-z-800.webp 800w', $srcset);
        $this->assertLessThan(strpos($srcset, '800w'), strpos($srcset, '480w'));
        // بدونِ واریانت / بدونِ ردیفِ Media / ورودیِ خالی → null (بدونِ srcset، رفتارِ قبلی)
        $this->assertNull(Media::srcsetFor('media/library/small.jpg'));
        $this->assertNull(Media::srcsetFor('homepage/no-row.png'));
        $this->assertNull(Media::srcsetFor(null));
    }

    public function test_homepage_instagram_fallback_image_gets_a_responsive_srcset(): void
    {
        SiteSetting::updateOrCreate(['key' => 'home.en.insta_showcase_fallback_image'], ['value' => 'media/library/insta-w.jpg']);
        Media::create([
            'original_name' => 'insta.jpg', 'disk' => 'public', 'disk_path' => 'media/library/insta-w.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'media/library/insta-w.webp',
            'responsive_paths' => [480 => 'media/library/insta-w-480.webp'],
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('insta-w-480.webp 480w" sizes="(max-width: 640px) 100vw, 270px"', $html);
    }

    public function test_homepage_hero_background_and_preload_use_the_webp_derivative(): void
    {
        SiteSetting::updateOrCreate(['key' => 'home.en.hero1_image'], ['value' => 'media/library/hero-y.jpg']);
        Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'media/library/hero-y.jpg',
            'url' => 'x', 'type' => 'image', 'webp_path' => 'media/library/hero-y.webp',
        ]);

        $html = $this->get('/')->assertOk()->getContent();

        $webp = Storage::disk('public')->url('media/library/hero-y.webp');
        // پس‌زمینه‌ی اسلاید و preload هر دو باید *همان* WebP باشند — وگرنه preload دوباره‌کاری می‌شود
        $this->assertStringContainsString("background:url('".$webp."')", $html);
        $this->assertStringContainsString('<link rel="preload" as="image" href="'.$webp.'" fetchpriority="high">', $html);
    }
}
