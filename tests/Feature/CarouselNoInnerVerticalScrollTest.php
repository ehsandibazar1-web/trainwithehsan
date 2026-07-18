<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * .learn-grid/.news-grid/.row-video/.carousel-track فقط overflow-x:auto را ست می‌کردند. طبق
 * اسپک CSS، وقتی overflow-x چیزی غیر از visible باشد ولی overflow-y ست نشده باشد، مرورگر
 * overflow-y را هم به auto تبدیل می‌کند (نه visible) — و چون کارت‌های داخل این ردیف‌ها چند
 * پیکسل از قابشان بلندتر بودند، یک اسکرول‌بار عمودیِ داخلیِ ناخواسته ساخته می‌شد که کشیدنِ لمسی
 * روی کارت‌ها را قاپ می‌زد (تأیید شده با اندازه‌گیری واقعیِ scrollHeight/clientHeight). حالا
 * overflow-y:hidden صریحاً ست شده تا این اسکرول داخلی هرگز دیده/فعال نشود، بدون تغییر در اسکرول
 * افقیِ خودِ کاروسل.
 */
class CarouselNoInnerVerticalScrollTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_carousels_have_no_inner_vertical_scroll(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $this->assertSame(
            4,
            substr_count($response->getContent(), 'overflow-y:hidden'),
            'Expected all four horizontal-scroll rules (row-video, learn-grid, news-grid, carousel-track) to explicitly close the inner vertical scroll.'
        );
    }

    public function test_turkish_homepage_carousels_have_no_inner_vertical_scroll(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $this->assertSame(
            4,
            substr_count($response->getContent(), 'overflow-y:hidden')
        );
    }
}
