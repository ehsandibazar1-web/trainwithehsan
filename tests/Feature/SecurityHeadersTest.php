<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * هدرهای امنیتیِ سطح مرورگر — دفاعِ مکمل، نه جایگزینِ پاکسازیِ HTML. Content-Security-Policy
 * عمداً Report-Only است (نه اجرایی) چون سایت اسکریپت/استایلِ inline زیادی دارد و چند اسکریپتِ
 * شخص‌ثالث که زیرمنبع‌های دقیقشان قابل تضمینِ کامل نیست — Report-Only هیچ درخواستی را مسدود
 * نمی‌کند، فقط در کنسولِ مرورگر گزارش می‌دهد.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_carries_safe_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        // نباید هدر اجراییِ CSP وجود داشته باشد — این نسخه عمداً فقط گزارش‌دهنده است
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_hsts_is_absent_on_a_plain_http_request(): void
    {
        // محیطِ تست پیش‌فرض https نیست — HSTS نباید ظاهر شود (وگرنه local/staging بدونِ SSL
        // را می‌شکند)
        $response = $this->get('/');

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_hsts_is_present_on_a_secure_request(): void
    {
        $response = $this->get('https://localhost/');

        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_known_third_party_scripts_are_allowed_in_the_reported_policy(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('www.googletagmanager.com', $csp);
        $this->assertStringContainsString('analytics.ahrefs.com', $csp);
        $this->assertStringContainsString('www.clarity.ms', $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_admin_panel_also_carries_safe_headers(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $response = $this->actingAs($owner)->get('/admin');

        $response->assertOk();
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }
}
