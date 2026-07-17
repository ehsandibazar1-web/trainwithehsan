<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google Tag Manager (which hosts a GA4 Configuration tag, set up in the GTM panel itself, not
 * in this codebase) / Microsoft Clarity — gated behind a cookie-consent banner (KVKK/GDPR): the
 * tracking scripts are only injected into the page after a visitor clicks "Accept"/"Kabul Et"
 * (or on a later visit if they already accepted, per localStorage), never unconditionally on
 * page load. With no GOOGLE_TAG_MANAGER_ID/MICROSOFT_CLARITY_ID configured, neither the banner
 * nor any tracking reference renders at all — this is the default, safe-by-default state for
 * any environment that hasn't set these two env vars (see config/services.php). GA4 was
 * originally loaded directly here via gtag.js; as of 2026-07-17 it moved inside the GTM
 * container per the user's explicit choice, so there is no more direct GA4 loading code to test.
 */
class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['services.google_tag_manager.id' => null]);
        config(['services.microsoft_clarity.id' => null]);

        parent::tearDown();
    }

    public function test_no_consent_banner_or_tracking_code_renders_when_nothing_is_configured(): void
    {
        config(['services.google_tag_manager.id' => null]);
        config(['services.microsoft_clarity.id' => null]);

        $response = $this->get('/');

        // خودِ کلاس CSS «.cookie-consent» همیشه در <style> هست (بی‌ضرر، مثل هر قانون CSS دیگری
        // که ممکن است روی یک صفحه استفاده نشود) — چیزی که واقعاً باید غایب باشد، خودِ عنصر بنر
        // و هر ارجاعی به اسکریپت‌های واقعیِ ردیابی است
        $response->assertOk();
        $response->assertDontSee('id="cookieConsent"', false);
        $response->assertDontSee('googletagmanager.com', false);
        $response->assertDontSee('clarity.ms', false);
    }

    public function test_consent_banner_renders_hidden_by_default_when_gtm_is_configured(): void
    {
        config(['services.google_tag_manager.id' => 'GTM-T42DJXQD']);
        config(['services.microsoft_clarity.id' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookieConsent" hidden', false);
        $response->assertSee('GTM-T42DJXQD', false);
        $response->assertSee('googletagmanager.com/gtm.js', false);
        // بدون <noscript> GTM — طبق تصمیم عمدیِ این پروژه: بازدیدکننده‌ی بدون جاوااسکریپت اصلاً
        // نمی‌تواند به بنر رضایت پاسخ بدهد، پس فایر بی‌قیدوشرط آن iframe درست همان چیزی است که
        // این مکانیزم رضایت می‌خواهد جلویش را بگیرد
        $response->assertDontSee('ns.html', false);
    }

    public function test_consent_banner_renders_when_only_clarity_is_configured(): void
    {
        config(['services.google_tag_manager.id' => null]);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookieConsent" hidden', false);
        $response->assertSee('xnvo1fc5b6', false);
        $response->assertSee('clarity.ms/tag', false);
    }

    public function test_turkish_home_page_renders_the_turkish_consent_banner_text(): void
    {
        config(['services.google_tag_manager.id' => 'GTM-T42DJXQD']);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('Kabul Et', false);
        $response->assertSee('Reddet', false);
        $response->assertSee('/tr/privacy-policy', false);
    }

    public function test_english_home_page_renders_the_english_consent_banner_text(): void
    {
        config(['services.google_tag_manager.id' => 'GTM-T42DJXQD']);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Accept', false);
        $response->assertSee('Decline', false);
    }
}
