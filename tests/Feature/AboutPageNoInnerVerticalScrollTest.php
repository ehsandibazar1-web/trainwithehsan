<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * .about-v5 (کل صفحه‌ی «درباره‌ی من» را دربر می‌گیرد) فقط overflow-x:hidden داشت. طبق اسپک CSS،
 * چون overflow-y صریح نبود، مرورگر آن را هم auto حساب می‌کرد — و چون افکت‌های تزئینی (blur/
 * transform) چند پیکسل از قاب بیرون می‌زدند، کل صفحه یک اسکرول عمودیِ داخلیِ ناخواسته پیدا
 * می‌کرد (عنوان، متن، و حتی بخش «Credentials & Achievements» پایین‌تر را هم در بر می‌گرفت، چون
 * همه‌شان فرزند همین یک wrapper هستند). دقیقاً همان باگیِ که روی کاروسل‌های صفحه‌ی اصلی بود.
 */
class AboutPageNoInnerVerticalScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_about_page_has_no_inner_vertical_scroll(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $response->assertSee('.about-v5{overflow-x:hidden;overflow-y:hidden', false);
    }

    public function test_turkish_about_page_has_no_inner_vertical_scroll(): void
    {
        $response = $this->get('/tr/about');

        $response->assertOk();
        $response->assertSee('.about-v5{overflow-x:hidden;overflow-y:hidden', false);
    }
}
