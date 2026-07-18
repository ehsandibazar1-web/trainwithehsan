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
        // نباید هدر اجراییِ CSP وجود داشته باشد — این نسخه عمداً فقط گزارش‌دهنده است
        $this->assertFalse($response->headers->has('Content-Security-Policy'));
    }

    public function test_known_third_party_scripts_are_allowed_in_the_reported_policy(): void
    {
        $csp = $this->get('/')->headers->get('Content-Security-Policy-Report-Only');

        $this->assertStringContainsString('fonts.googleapis.com', $csp);
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
