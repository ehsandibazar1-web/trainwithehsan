<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // بدونِ RefreshDatabase این تست صفحه‌ی اصلی را روی دیتابیسِ درون‌حافظه‌ایِ migrate-نشده باز
    // می‌کرد و همیشه 500 می‌گرفت — همان «شکستِ شناخته‌شده»ی ثبت‌شده در CLAUDE.md که مانعِ
    // سبزشدنِ CI می‌شد؛ با migrate، همان چیزی را می‌سنجد که ادعا می‌کند: صفحه‌ی اصلی 200 می‌دهد
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
