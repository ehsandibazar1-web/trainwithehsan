<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GA4 / Microsoft Clarity — gated behind a cookie-consent banner (KVKK/GDPR): the tracking
 * scripts are only injected into the page after a visitor clicks "Accept"/"Kabul Et" (or on a
 * later visit if they already accepted, per localStorage), never unconditionally on page load.
 * With no GOOGLE_ANALYTICS_ID/MICROSOFT_CLARITY_ID configured, neither the banner nor any
 * tracking reference renders at all — this is the default, safe-by-default state for any
 * environment that hasn't set these two env vars (see config/services.php).
 */
class AnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['services.google_analytics.id' => null]);
        config(['services.microsoft_clarity.id' => null]);

        parent::tearDown();
    }

    public function test_no_consent_banner_or_tracking_code_renders_when_nothing_is_configured(): void
    {
        config(['services.google_analytics.id' => null]);
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

    public function test_consent_banner_renders_hidden_by_default_when_ga4_is_configured(): void
    {
        config(['services.google_analytics.id' => 'G-TESTID123']);
        config(['services.microsoft_clarity.id' => null]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookieConsent" hidden', false);
        $response->assertSee('G-TESTID123', false);
        $response->assertSee('googletagmanager.com/gtag/js', false);
    }

    public function test_consent_banner_renders_when_only_clarity_is_configured(): void
    {
        config(['services.google_analytics.id' => null]);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="cookieConsent" hidden', false);
        $response->assertSee('xnvo1fc5b6', false);
        $response->assertSee('clarity.ms/tag', false);
    }

    public function test_turkish_home_page_renders_the_turkish_consent_banner_text(): void
    {
        config(['services.google_analytics.id' => 'G-TESTID123']);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('Kabul Et', false);
        $response->assertSee('Reddet', false);
        $response->assertSee('/tr/privacy-policy', false);
    }

    public function test_english_home_page_renders_the_english_consent_banner_text(): void
    {
        config(['services.google_analytics.id' => 'G-TESTID123']);
        config(['services.microsoft_clarity.id' => 'xnvo1fc5b6']);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Accept', false);
        $response->assertSee('Decline', false);
    }
}
