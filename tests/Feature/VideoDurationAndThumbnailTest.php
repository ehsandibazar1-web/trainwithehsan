<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Services\Media\MediaProcessor;
use App\Services\Media\VideoMetadataService;
use App\Services\Seo\VideoSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Video SEO — H2 (duration on VideoObject/Sitemap, خوانده‌شده بدونِ وابستگی از هدرِ mp4) و
 * H3 (تامبنیلِ تضمین‌شده: fallbackِ سراسری تا هیچ ویدیوی پشتیبانی‌شده به‌خاطرِ نبودِ تامبنیل حذف نشود).
 */
class VideoDurationAndThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VideoSchemaService
    {
        return app(VideoSchemaService::class);
    }

    private function box(string $type, string $payload): string
    {
        return pack('N', 8 + strlen($payload)).$type.$payload;
    }

    // یک mp4 حداقلی و معتبر (ftyp + moov>mvhd) با timescale/duration مشخص — finfo آن را video/mp4
    // می‌شناسد (major brand=isom) و parser مدتش را می‌خواند
    private function craftMp4(int $timescale, int $duration, bool $v1 = false): string
    {
        $ftyp = $this->box('ftyp', 'isom'.pack('N', 0x200).'isommp42');

        if ($v1) {
            $mvhd = $this->box('mvhd', "\x01\x00\x00\x00".str_repeat("\x00", 16).pack('N', $timescale).pack('J', $duration).str_repeat("\x00", 80));
        } else {
            $mvhd = $this->box('mvhd', "\x00\x00\x00\x00".pack('N', 0).pack('N', 0).pack('N', $timescale).pack('N', $duration).str_repeat("\x00", 80));
        }

        return $ftyp.$this->box('moov', $mvhd);
    }

    private function tempMp4(int $timescale, int $duration, bool $v1 = false): string
    {
        $path = tempnam(sys_get_temp_dir(), 'vid').'.mp4';
        file_put_contents($path, $this->craftMp4($timescale, $duration, $v1));

        return $path;
    }

    // ---- H2: pure-PHP duration parser -----------------------------------------

    public function test_parses_mp4_duration_from_mvhd_v0(): void
    {
        $path = $this->tempMp4(1000, 150000); // 150s
        $this->assertSame(150, (new VideoMetadataService)->durationSeconds($path));
        @unlink($path);
    }

    public function test_parses_mp4_duration_from_mvhd_v1_64bit(): void
    {
        $path = $this->tempMp4(600, 600 * 42, true); // 42s, 64-bit fields
        $this->assertSame(42, (new VideoMetadataService)->durationSeconds($path));
        @unlink($path);
    }

    public function test_returns_null_for_non_mp4_or_garbage(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'junk');
        file_put_contents($path, random_bytes(512));
        $this->assertNull((new VideoMetadataService)->durationSeconds($path));
        @unlink($path);
    }

    public function test_returns_null_for_missing_file(): void
    {
        $this->assertNull((new VideoMetadataService)->durationSeconds('/no/such/file.mp4'));
    }

    public function test_to_iso8601_formatting(): void
    {
        $this->assertSame('PT2M30S', VideoMetadataService::toIso8601(150));
        $this->assertSame('PT1H1M1S', VideoMetadataService::toIso8601(3661));
        $this->assertSame('PT45S', VideoMetadataService::toIso8601(45));
        $this->assertSame('PT1H', VideoMetadataService::toIso8601(3600));
        $this->assertNull(VideoMetadataService::toIso8601(0));
        $this->assertNull(VideoMetadataService::toIso8601(null));
    }

    public function test_iso8601_to_seconds(): void
    {
        $this->assertSame(150, VideoMetadataService::iso8601ToSeconds('PT2M30S'));
        $this->assertSame(3661, VideoMetadataService::iso8601ToSeconds('PT1H1M1S'));
        $this->assertSame(3600, VideoMetadataService::iso8601ToSeconds('PT1H'));
        $this->assertNull(VideoMetadataService::iso8601ToSeconds('not-a-duration'));
        $this->assertNull(VideoMetadataService::iso8601ToSeconds(null));
    }

    // ---- H2: MediaProcessor probes + stores duration for videos ----------------

    public function test_media_processor_stores_duration_for_a_video_upload(): void
    {
        Storage::fake('public');
        $src = $this->tempMp4(1000, 150000);
        $file = new UploadedFile($src, 'clip.mp4', 'video/mp4', null, true);

        $media = app(MediaProcessor::class)->store($file, 'videos');
        $media->refresh();

        $this->assertSame('video', $media->type);
        $this->assertSame(150, (int) $media->duration_seconds);
        $this->assertSame('PT2M30S', $media->duration_iso8601);
        @unlink($src);
    }

    // ---- H2: duration flows into VideoObject + sitemap -------------------------

    public function test_homepage_self_hosted_video_includes_duration_when_media_has_it(): void
    {
        Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'homepage/videos/clip.mp4',
            'url' => 'http://localhost/storage/homepage/videos/clip.mp4', 'type' => 'video', 'duration_seconds' => 150,
        ]);

        $out = $this->service()->forHomepage([
            'video1_caption' => 'Why train',
            'video1_file' => 'homepage/videos/clip.mp4',
            'video1_thumb' => 'homepage/videos/thumb.jpg',
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame('PT2M30S', $out[0]['duration']);
    }

    public function test_article_self_hosted_video_includes_duration_from_matching_media_row(): void
    {
        Media::create([
            'original_name' => 'demo.mp4', 'disk' => 'public', 'disk_path' => 'videos/demo.mp4',
            'url' => 'http://localhost/storage/videos/demo.mp4', 'type' => 'video', 'duration_seconds' => 90,
        ]);

        $article = Article::create([
            'locale' => 'en', 'title' => 'Demo', 'slug' => 'demo-dur-'.uniqid(),
            'image_path' => 'articles/hero.jpg', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => '<p><a href="/storage/videos/demo.mp4">Technique demo</a></p>',
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame(url('/storage/videos/demo.mp4'), $out[0]['contentUrl']);
        $this->assertSame('PT1M30S', $out[0]['duration']);
    }

    public function test_sitemap_emits_video_duration_for_self_hosted_video(): void
    {
        Media::create([
            'original_name' => 'demo.mp4', 'disk' => 'public', 'disk_path' => 'videos/demo.mp4',
            'url' => 'http://localhost/storage/videos/demo.mp4', 'type' => 'video', 'duration_seconds' => 90,
        ]);

        Article::create([
            'locale' => 'en', 'title' => 'Sitemap Duration', 'slug' => 'sitemap-duration',
            'image_path' => 'articles/hero.jpg', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => '<p><a href="/storage/videos/demo.mp4">Technique demo</a></p>',
        ]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<video:duration>90</video:duration>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    // ---- H3: guaranteed thumbnails --------------------------------------------

    public function test_video_without_a_thumbnail_uses_the_site_default_when_configured(): void
    {
        SiteSetting::set('about.en.hero_image', 'about/ehsan.jpg', 'about');

        // ویمئو تامبنیلِ مشتق ندارد و مقاله عکسِ شاخص ندارد — قبلاً حذف می‌شد؛ حالا fallbackِ سراسری
        $article = Article::create([
            'locale' => 'en', 'title' => 'No Hero', 'slug' => 'no-hero-'.uniqid(),
            'image_path' => null, 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => '<p><a href="https://vimeo.com/123456789">Seminar</a></p>',
        ]);

        $out = $this->service()->forArticle($article);

        $this->assertCount(1, $out);
        $this->assertSame(asset('storage/about/ehsan.jpg'), $out[0]['thumbnailUrl']);
    }

    public function test_video_is_still_skipped_when_no_thumbnail_and_no_site_default(): void
    {
        // بدونِ عکسِ شاخص و بدونِ about hero → همان رفتارِ محافظه‌کارِ قبلی: حذف
        $article = Article::create([
            'locale' => 'en', 'title' => 'Nothing', 'slug' => 'nothing-'.uniqid(),
            'image_path' => null, 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now()->subDay(),
            'body' => '<p><a href="https://vimeo.com/123456789">Seminar</a></p>',
        ]);

        $this->assertCount(0, $this->service()->forArticle($article));
    }

    public function test_homepage_self_hosted_video_without_thumb_uses_site_default(): void
    {
        SiteSetting::set('about.en.hero_image', 'about/ehsan.jpg', 'about');

        $out = $this->service()->forHomepage([
            'video1_caption' => 'No thumb',
            'video1_file' => 'homepage/videos/clip.mp4', // بدونِ video1_thumb
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame(asset('storage/about/ehsan.jpg'), $out[0]['thumbnailUrl']);
    }
}
