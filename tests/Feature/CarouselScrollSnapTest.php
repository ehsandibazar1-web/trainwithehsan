<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * scroll-snap-type:x mandatory روی هر سه ردیف افقی موبایل (ویدیو، دوره‌ها، مقالات) یک باگ
 * شناخته‌شده‌ی اندروید کروم داشت: کشیدنِ عمودی که روی این ردیف‌ها شروع می‌شد "گیر" می‌کرد
 * (نه اسکرول صفحه، نه اسکرول کاروسل) و بعد ناگهان می‌جهید. mandatory یعنی مرورگر باید دقیقاً
 * روی یک نقطهٔ اسنپ بایستد و ژست لمسی را برای رسیدن به آن قاپ می‌زند؛ proximity فقط وقتی
 * نزدیک یک نقطهٔ اسنپ هست می‌چسباند و ژست را قاپ نمی‌زند.
 */
class CarouselScrollSnapTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_uses_proximity_snap_not_mandatory_on_mobile_carousels(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('scroll-snap-type:x mandatory', false);
        $this->assertSame(
            4,
            substr_count($response->getContent(), 'scroll-snap-type:x proximity'),
            'Expected all four horizontal-scroll rules (row-video, learn-grid, news-grid, carousel-track) to use proximity.'
        );
    }

    public function test_turkish_homepage_uses_proximity_snap_not_mandatory_on_mobile_carousels(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertDontSee('scroll-snap-type:x mandatory', false);
        $this->assertSame(
            4,
            substr_count($response->getContent(), 'scroll-snap-type:x proximity'),
            'Expected all four horizontal-scroll rules (row-video, learn-grid, news-grid, carousel-track) to use proximity.'
        );
    }
}
