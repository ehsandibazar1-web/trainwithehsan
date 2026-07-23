<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کشِ کاملِ HTML روی لبه‌ی Cloudflare — این تست‌ها سه گاردِ ایمنی را تثبیت می‌کنند تا کش‌کردنِ
 * صفحات هرگز فرم‌ها/سشن را نشکند: (۱) پیش‌فرض خاموش = رفتارِ امروز، (۲) وقتی روشن است فقط صفحاتِ
 * عمومیِ allowlist کش‌پذیر می‌شوند و کوکیِ سشنِ ناشناس حذف می‌شود، (۳) اندپوینتِ /csrf-token
 * همیشه توکنِ تازه و کش‌ناپذیر می‌دهد تا فرم‌ها روی HTMLِ کش‌شده هم کار کنند.
 */
class EdgeCacheTest extends TestCase
{
    use RefreshDatabase;

    private function sessionCookie(): string
    {
        return (string) config('session.cookie');
    }

    public function test_csrf_token_endpoint_returns_a_token_and_is_never_cached(): void
    {
        $response = $this->get('/csrf-token');

        $response->assertOk();
        $response->assertJsonStructure(['token']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_by_default_public_pages_are_not_edge_cacheable(): void
    {
        // پیش‌فرض: کلید خاموش است → هیچ هدرِ کشِ اشتراکی، و کوکیِ سشن سرِ جایش (رفتارِ امروز)
        $response = $this->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
        $response->assertCookie($this->sessionCookie());
    }

    public function test_when_enabled_anonymous_public_page_becomes_edge_cacheable_without_session_cookie(): void
    {
        SiteSetting::set('edge_cache.enabled', '1');
        SiteSetting::set('edge_cache.ttl', '600');

        $response = $this->get('/');

        $response->assertOk();
        // به Cloudflare اجازه‌ی کشِ اشتراکی داده شده...
        $this->assertStringContainsString('s-maxage=600', (string) $response->headers->get('Cache-Control'));
        // ...و کوکیِ سشن/XSRF از پاسخِ ناشناس حذف شده تا نسخه‌ی کش‌شده بی‌کاربر بماند
        $response->assertCookieMissing($this->sessionCookie());
        $response->assertCookieMissing('XSRF-TOKEN');
    }

    public function test_when_enabled_a_visitor_with_a_session_cookie_is_not_edge_cached(): void
    {
        SiteSetting::set('edge_cache.enabled', '1');

        // بازدیدکننده‌ای که سشن دارد (مثلِ ادمینِ لاگین‌شده) نباید پاسخِ کش‌پذیر بگیرد
        $response = $this->withUnencryptedCookie($this->sessionCookie(), 'existing-session')
            ->get('/');

        $response->assertOk();
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }

    public function test_when_enabled_non_allowlisted_routes_are_not_edge_cached(): void
    {
        SiteSetting::set('edge_cache.enabled', '1');

        // /csrf-token عمداً در allowlist نیست — نباید هدرِ کشِ اشتراکی بگیرد
        $response = $this->get('/csrf-token');

        $response->assertOk();
        $this->assertStringNotContainsString('s-maxage', (string) $response->headers->get('Cache-Control'));
    }
}
