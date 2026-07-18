<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * فاز ۶: media:backfill حالا علاوه بر Article/Page، تصاویرِ CMS (home./about./footer.) که از
 * قبلِ DAM در SiteSetting نشسته‌اند را هم به کتابخانه‌ی رسانه اضافه می‌کند — شاملِ مسیرهای داخلِ
 * JSON blobها — بدون آنکه متن/عنوان/URL را به‌اشتباه ثبت کند.
 */
class MediaBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_registers_site_setting_images_including_json_blobs_and_ignores_non_paths(): void
    {
        Storage::fake('public');

        // یک تصویرِ واقعی برای هیرو (تا تولیدِ مشتقات هم بررسی شود)
        UploadedFile::fake()->image('h1.jpg', 800, 600)->storeAs('homepage/hero', 'h1.jpg', 'public');
        // فایل‌های واقعیِ دیگر (محتوایشان مهم نیست، فقط باید روی دیسک باشند)
        Storage::disk('public')->put('about/hero/about.jpg', 'x');
        Storage::disk('public')->put('footer/logo.png', 'x');
        Storage::disk('public')->put('homepage/members/m1.jpg', 'x'); // داخلِ JSON

        // مقادیرِ SiteSetting: مسیرِ ساده، متن، URL، و JSON blob
        SiteSetting::set('home.en.hero1_image', 'homepage/hero/h1.jpg');
        SiteSetting::set('home.en.hero1_title', 'Welcome to the site'); // متن — نباید adopt شود
        SiteSetting::set('home.en.insta_url', 'https://instagram.com/x'); // URL — نباید
        SiteSetting::set('about.en.hero_image', 'about/hero/about.jpg');
        SiteSetting::set('footer.en.logo', 'footer/logo.png');
        SiteSetting::set('home.en.members', json_encode([['name' => 'A', 'photo' => 'homepage/members/m1.jpg']]));

        $this->artisan('media:backfill')->assertSuccessful();

        // هر چهار فایلِ واقعی به کتابخانه اضافه شده‌اند
        $this->assertDatabaseHas('media', ['disk_path' => 'homepage/hero/h1.jpg']);
        $this->assertDatabaseHas('media', ['disk_path' => 'about/hero/about.jpg']);
        $this->assertDatabaseHas('media', ['disk_path' => 'footer/logo.png']);
        $this->assertDatabaseHas('media', ['disk_path' => 'homepage/members/m1.jpg']);

        // تصویرِ واقعیِ هیرو باید image و دارای WebP باشد
        $hero = Media::where('disk_path', 'homepage/hero/h1.jpg')->first();
        $this->assertSame('image', $hero->type);
        $this->assertNotNull($hero->webp_path);

        // متن و URL هرگز نباید ردیفِ Media بسازند
        $this->assertDatabaseMissing('media', ['disk_path' => 'Welcome to the site']);
        $this->assertDatabaseMissing('media', ['disk_path' => 'https://instagram.com/x']);
        $this->assertSame(4, Media::count());
    }

    public function test_backfill_is_idempotent(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('about/hero/about.jpg', 'x');
        SiteSetting::set('about.en.hero_image', 'about/hero/about.jpg');

        $this->artisan('media:backfill')->assertSuccessful();
        $this->artisan('media:backfill')->assertSuccessful();

        // اجرای دوباره ردیفِ تکراری نمی‌سازد
        $this->assertSame(1, Media::where('disk_path', 'about/hero/about.jpg')->count());
    }
}
