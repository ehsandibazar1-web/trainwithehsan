<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * روی موبایل، کشیدن افقیِ کاروسل‌های «دوره‌ها» و «مقالات» در لبه‌ی اسکرول به اسکرول عمودیِ کل
 * صفحه سرریز می‌کرد (scroll chaining) و حس ناپایدار بالا-پایین‌رفتن می‌داد. touch-action:pan-x
 * جهت لمس را روی این کاروسل‌ها به افقی قفل می‌کند و overscroll-behavior-x:contain از سرریز شدن
 * اسکرول به صفحه‌ی والد جلوگیری می‌کند. کلاس carousel-track بین هر دو کاروسل مشترک است.
 */
class CarouselTouchDirectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_locks_carousel_touch_scrolling_to_horizontal(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('touch-action:pan-x;overscroll-behavior-x:contain;', false);
    }

    public function test_turkish_homepage_locks_carousel_touch_scrolling_to_horizontal(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('touch-action:pan-x;overscroll-behavior-x:contain;', false);
    }
}
