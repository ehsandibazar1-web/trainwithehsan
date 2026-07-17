<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * کارت‌های «Courses Section» صفحه‌ی اصلی قبلاً همیشه به یک مسیر ثابت و ناموجود (/courses،
 * /tr/courses — بدون route) لینک می‌دادند و هیچ فیلدی برای تغییرش در پنل نبود. حالا هر کارت یک
 * فیلد لینک اختصاصی (course{N}_link) در Homepage Settings دارد؛ اگر خالی بماند، به‌جای آن مسیر
 * ۴۰۴، به صفحه‌ی بلاگ همان زبان لینک می‌دهد تا کارت هیچ‌وقت به یک صفحه‌ی ناموجود اشاره نکند.
 */
class HomepageCourseLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_course_cards_default_to_the_blog_page_when_no_link_is_set(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        // سه‌بار (یکی برای هر کارت) — نه یک لینک ثابت مشترک مثل قبل
        $response->assertSee('href="'.url('/blog').'" class="l-box reveal"', false);
        $this->assertSame(
            3,
            substr_count($response->getContent(), 'href="'.url('/blog').'" class="l-box reveal"')
        );
    }

    public function test_english_course_card_uses_its_configured_link(): void
    {
        SiteSetting::set('home.en.course1_link', '/blog/some-article');
        SiteSetting::set('home.en.course2_link', 'https://external.example.com/enroll');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.url('/blog/some-article').'"', false);
        $response->assertSee('href="https://external.example.com/enroll"', false);
    }

    public function test_turkish_course_cards_default_to_the_turkish_blog_page(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertSee('href="'.url('/tr/blog').'" class="l-box reveal"', false);
        $this->assertSame(
            3,
            substr_count($response->getContent(), 'href="'.url('/tr/blog').'" class="l-box reveal"')
        );
    }
}
