<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * روی موبایل، کارت‌های «دوره‌ها» و «مقالات» صفحه‌ی اصلی بیش‌ازحد کشیده بودند (به‌خصوص چون
 * .img-news موبایل (220px) حتی از دسکتاپ (170px) هم بلندتر بود) و همین حین اسکرول حس ناپایداری
 * می‌داد. این تست فقط مقادیر CSS مخصوص موبایل را چک می‌کند — عرض/رنگ/ساختار کارت‌ها دست‌نخورده
 * می‌ماند، فقط نسبت ارتفاع در بریک‌پوینت موبایل تغییر کرده.
 */
class HomepageMobileCardSizingTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_homepage_has_shorter_mobile_card_proportions(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('@media(max-width:600px){.img-learn{aspect-ratio:362/190}}', false);
        $response->assertSee('@media(max-width:600px){.l-title{min-height:42px;padding-top:6px}}', false);
        $response->assertSee('@media(max-width:767px){.img-news{height:150px}}', false);
        $response->assertSee('@media(max-width:767px){.news-short-text{min-height:60px;max-height:60px}}', false);
        // نباید دیگر مقدار قدیمی (بلندتر از دسکتاپ) وجود داشته باشد
        $response->assertDontSee('@media(max-width:767px){.img-news{height:220px}}', false);
    }

    public function test_turkish_homepage_has_shorter_mobile_card_proportions(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('@media(max-width:600px){.img-learn{aspect-ratio:362/190}}', false);
        $response->assertSee('@media(max-width:600px){.l-title{min-height:42px;padding-top:6px}}', false);
        $response->assertSee('@media(max-width:767px){.img-news{height:150px}}', false);
        $response->assertSee('@media(max-width:767px){.news-short-text{min-height:60px;max-height:60px}}', false);
        $response->assertDontSee('@media(max-width:767px){.img-news{height:220px}}', false);
    }
}
