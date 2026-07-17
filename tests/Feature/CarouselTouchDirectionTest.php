<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * روی موبایل، کشیدن افقیِ کاروسل‌های «دوره‌ها» و «مقالات» در لبه‌ی اسکرول به اسکرول عمودیِ کل
 * صفحه سرریز می‌کرد (scroll chaining) و حس ناپایدار بالا-پایین‌رفتن می‌داد. تلاش اول با
 * touch-action:pan-x کل اسکرول لمسی را روی برخی دستگاه‌ها غیرفعال کرد و برگردانده شد؛ این نسخه
 * فقط overscroll-behavior-x:contain را اضافه می‌کند که ریسک کمتری دارد و رفتار پیش‌فرض اسکرول
 * افقی مرورگر را دست‌نخورده می‌گذارد. کلاس carousel-track بین هر دو کاروسل مشترک است.
 */
class CarouselTouchDirectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_contains_carousel_scroll_from_chaining_into_the_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('overscroll-behavior-x:contain;', false);
    }

    public function test_turkish_homepage_contains_carousel_scroll_from_chaining_into_the_page(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('overscroll-behavior-x:contain;', false);
    }
}
