<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * وقتی «Menu Settings» تنظیم نشده باشد، هدر و فوتر از یک منوی پیش‌فرض هاردکدشده در بلید استفاده
 * می‌کنند. این پیش‌فرض یک آیتم «Courses»/«Kurslar» داشت که به /courses یا /tr/courses اشاره
 * می‌کرد — مسیری که هیچ route‌ای برایش تعریف نشده (۴۰۴ دائمی). حالا تا وقتی صفحه‌ی اختصاصی دوره‌ها
 * ساخته نشده، پیش‌فرض به صفحه‌ی بلاگ اشاره می‌کند.
 */
class DefaultNavigationLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_english_default_menu_and_footer_do_not_link_to_the_missing_courses_route(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('href="'.url('/courses').'"', false);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="'.url('/blog').'"'),
            'Expected both the nav "Courses" item and the footer "Courses" item to point at /blog.'
        );
    }

    public function test_turkish_default_menu_and_footer_do_not_link_to_the_missing_courses_route(): void
    {
        $response = $this->get('/tr');

        $response->assertOk();
        $response->assertDontSee('href="'.url('/tr/courses').'"', false);
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($response->getContent(), 'href="'.url('/tr/blog').'"'),
            'Expected both the nav "Kurslar" item and the footer "Kurslar" item to point at /tr/blog.'
        );
    }
}
