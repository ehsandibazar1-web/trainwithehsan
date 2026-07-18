<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * .panel-menu (منوی همبرگریِ موبایل) فقط overflow-y:auto داشت، بدون overflow-x صریح. طبق اسپک
 * CSS، مرورگر overflow-x را هم auto حساب می‌کند — همان الگویی که روی کاروسل‌های صفحه‌ی اصلی و
 * صفحه‌ی درباره‌ی من پیدا شد، این‌جا برعکس (محور افقی). فعلاً اسکرول واقعی اندازه‌گیری‌شده صفر
 * بود، ولی overflow-x:hidden به‌صورت پیش‌گیرانه اضافه شد تا اگر بعداً یک آیتم منو طولانی‌تر شد،
 * همین باگ رخ ندهد.
 */
class NavPanelNoInnerHorizontalScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_nav_panel_has_no_inner_horizontal_scroll(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('overflow-y:auto;overflow-x:hidden;', false);
    }

    public function test_turkish_nav_panel_has_no_inner_horizontal_scroll(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('overflow-y:auto;overflow-x:hidden;', false);
    }
}
