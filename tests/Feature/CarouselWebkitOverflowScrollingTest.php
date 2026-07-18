<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * -webkit-overflow-scrolling:touch یک هک قدیمیِ مخصوص iOS<13 است که روی مرورگرهای مدرن (از جمله
 * کروم اندروید که این باگ گزارش شد) هیچ اثر مفیدی ندارد. وقتی روی یک جعبه‌ی قابل‌اسکرول‌افقی که
 * فرزندانِ overflow:hidden دارد (کارت‌های ویدیو/دوره/مقاله) قرار بگیرد، یک باگ رندرینگ شناخته‌شده
 * دارد که باعث می‌شود عکسِ داخلِ قاب حین اسکرولِ لمسی جابه‌جا/لغزنده به نظر برسد. حذف این تنظیم
 * روی هر چهار کاروسل (ویدیو، دوره‌ها، مقالات، و carousel-track مشترک) هیچ رفتار مفیدی را از بین
 * نمی‌برد.
 */
class CarouselWebkitOverflowScrollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_no_longer_uses_webkit_overflow_scrolling(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('-webkit-overflow-scrolling', false);
    }

    public function test_turkish_homepage_no_longer_uses_webkit_overflow_scrolling(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertDontSee('-webkit-overflow-scrolling', false);
    }
}
