<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * عکس‌های سه ردیف افقی موبایل (ویدیو، دوره‌ها، مقالات) قبلاً به‌صورت background-image روی یک
 * div بودند — که در اسکرول لمسیِ موبایل باعث نقاشی‌شدن مجدد هر فریم می‌شد و حس لغزیدن/ناپایداری
 * تصویر داخل قاب می‌داد. حالا از تگ img واقعی با object-fit:cover استفاده می‌شود (دقیقاً همان
 * تکنیکی که ehsandibazar.com اصلی استفاده می‌کند) تا مرورگر بتواند آن را روی لایه‌ی جدای GPU
 * کامپوزیت کند.
 */
class HomepageCardImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_card_renders_a_real_img_tag_when_an_image_is_set(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('course1.jpg');
        $path = $file->store('homepage', 'public');
        SiteSetting::set('home.en.course1_image', $path);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<img src="'.asset('storage/'.$path).'"', false);
        $response->assertDontSee('background-image:url', false);
    }

    public function test_course_card_falls_back_to_the_letter_placeholder_when_no_image_is_set(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<b>In-Person</b>', false);
    }
}
