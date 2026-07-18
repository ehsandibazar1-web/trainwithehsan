<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\SiteSetting;
use App\Services\Seo\VideoSchemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Video SEO — VideoObject JSON-LD برای ویدیوهای صفحه‌ی اصلی (ردیفِ ویدیو + ویدیوهای اعضا).
 * فقط داده‌ی ساختاریِ نامرئی؛ کاملاً افزایشی و backward-compatible (بدونِ ویدیو، هیچ چیزی اضافه نمی‌شود).
 */
class VideoSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function service(): VideoSchemaService
    {
        return app(VideoSchemaService::class);
    }

    public function test_youtube_embed_video_becomes_a_videoobject_with_embed_url_and_derived_thumbnail(): void
    {
        $out = $this->service()->forHomepage([
            'video1_caption' => 'How the training works',
            'video1_embed' => 'https://www.youtube.com/watch?v=abcdefghijk',
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame('VideoObject', $out[0]['@type']);
        $this->assertSame('How the training works', $out[0]['name']);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
        // بدونِ تامبنیلِ آپلودشده → از شناسه‌ی یوتیوب ساخته می‌شود
        $this->assertSame('https://img.youtube.com/vi/abcdefghijk/hqdefault.jpg', $out[0]['thumbnailUrl']);
        $this->assertArrayNotHasKey('contentUrl', $out[0]);
    }

    public function test_self_hosted_video_becomes_a_videoobject_with_content_url_thumbnail_and_upload_date(): void
    {
        $media = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'homepage/videos/clip.mp4',
            'url' => 'http://localhost/storage/homepage/videos/clip.mp4', 'type' => 'video',
        ]);

        $out = $this->service()->forHomepage([
            'video2_caption' => 'Why train',
            'video2_file' => 'homepage/videos/clip.mp4',
            'video2_thumb' => 'homepage/videos/thumb.jpg',
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame(asset('storage/homepage/videos/clip.mp4'), $out[0]['contentUrl']);
        $this->assertSame(asset('storage/homepage/videos/thumb.jpg'), $out[0]['thumbnailUrl']);
        $this->assertSame($media->created_at->toIso8601String(), $out[0]['uploadDate']);
    }

    public function test_vimeo_embed_is_recognized(): void
    {
        $out = $this->service()->forHomepage([
            'video1_caption' => 'Vimeo clip',
            'video1_embed' => 'https://vimeo.com/123456789',
            'video1_thumb' => 'homepage/videos/v.jpg',
        ], []);

        $this->assertCount(1, $out);
        $this->assertSame('https://player.vimeo.com/video/123456789', $out[0]['embedUrl']);
    }

    public function test_member_video_uses_the_member_photo_as_thumbnail(): void
    {
        $out = $this->service()->forHomepage([], [
            ['name' => 'Sara', 'video_embed' => 'https://youtu.be/abcdefghijk', 'photo' => 'homepage/members/sara.jpg'],
        ]);

        $this->assertCount(1, $out);
        $this->assertStringContainsString('Sara', $out[0]['name']);
        $this->assertSame(asset('storage/homepage/members/sara.jpg'), $out[0]['thumbnailUrl']);
        $this->assertSame('https://www.youtube.com/embed/abcdefghijk', $out[0]['embedUrl']);
    }

    public function test_video_with_no_source_is_skipped(): void
    {
        // فقط کپشن، بدونِ embed و بدونِ فایل → ویدیویی وجود ندارد
        $out = $this->service()->forHomepage(['video1_caption' => 'Just a caption'], []);

        $this->assertSame([], $out);
    }

    public function test_video_with_a_source_but_no_thumbnail_is_skipped(): void
    {
        // Vimeo بدونِ عکسِ آپلودشده → تامبنیلِ ثابت ندارد → VideoObjectِ معتبر نمی‌شود، رد می‌شود
        $out = $this->service()->forHomepage([
            'video1_caption' => 'No thumb',
            'video1_embed' => 'https://vimeo.com/123456789',
        ], []);

        $this->assertSame([], $out);
    }

    public function test_empty_settings_produce_no_schema(): void
    {
        $this->assertSame([], $this->service()->forHomepage([], []));
    }

    public function test_homepage_emits_videoobject_json_ld_when_a_video_is_configured(): void
    {
        SiteSetting::set('home.en.video1_caption', 'Intro video', 'homepage');
        SiteSetting::set('home.en.video1_embed', 'https://www.youtube.com/watch?v=abcdefghijk', 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('VideoObject')
            ->assertSee('youtube.com/embed/abcdefghijk')
            // داده‌ی ساختاریِ موجود (Organization/Person) دست‌نخورده می‌ماند
            ->assertSee('Organization');
    }

    public function test_homepage_adds_no_videoobject_when_no_video_is_configured(): void
    {
        // نصبِ تازه بدونِ ویدیو — خروجی باید مثلِ قبل باشد: هیچ VideoObjectی، ولی Organization سرِ جایش
        $this->get('/')
            ->assertOk()
            ->assertDontSee('VideoObject')
            ->assertSee('Organization');
    }
}
