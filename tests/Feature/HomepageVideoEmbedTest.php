<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ردیفِ ویدیوی صفحه‌ی اصلی حالا از همان موتورِ embed (App\Services\Content\EmbedRenderer) عبور
 * می‌کند: یوتیوب/ویمئو/اینستاگرام/تیک‌تاک شناخته می‌شوند و کارت‌ها data-embed-kind/src می‌گیرند؛
 * مودالِ ویدیو با کلیک پخش‌کننده/بلاک‌کوت را می‌سازد. لینکِ ناشناخته (آپارات) مثلِ قبل iframe می‌ماند.
 */
class HomepageVideoEmbedTest extends TestCase
{
    use RefreshDatabase;

    public function test_youtube_embed_becomes_an_iframe_kind_with_nocookie_src(): void
    {
        SiteSetting::set('home.en.video1_embed', 'https://www.youtube.com/watch?v=abcdefghijk', 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-embed-kind="iframe"', false)
            ->assertSee('youtube-nocookie.com/embed/abcdefghijk', false);
    }

    public function test_instagram_embed_becomes_an_instagram_kind(): void
    {
        SiteSetting::set('home.en.video1_embed', 'https://www.instagram.com/reel/ABC123def/', 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-embed-kind="instagram"', false)
            ->assertSee('instagram.com/reel/ABC123def/', false);
    }

    public function test_tiktok_embed_becomes_a_tiktok_kind_with_video_id(): void
    {
        SiteSetting::set('home.en.video1_embed', 'https://www.tiktok.com/@someone/video/1234567890123456789', 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-embed-kind="tiktok"', false)
            ->assertSee('data-embed-id="1234567890123456789"', false);
    }

    public function test_unknown_provider_falls_back_to_iframe_with_the_raw_url(): void
    {
        // آپارات و هر لینکِ ناشناخته — رفتارِ قبلی حفظ می‌شود: iframe با همان لینک
        SiteSetting::set('home.en.video1_embed', 'https://www.aparat.com/v/abc123', 'homepage');

        $this->get('/')
            ->assertOk()
            ->assertSee('data-embed-kind="iframe"', false)
            ->assertSee('aparat.com/v/abc123', false);
    }

    public function test_no_third_party_iframe_or_script_is_present_before_interaction(): void
    {
        SiteSetting::set('home.en.video1_embed', 'https://www.youtube.com/watch?v=abcdefghijk', 'homepage');

        // کارت فقط یک facade است — هیچ <iframe> ای تا کلیکِ کاربر در HTMLِ سرور نیست
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringNotContainsString('<iframe', $html);
    }
}
