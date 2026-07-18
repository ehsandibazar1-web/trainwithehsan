<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPageSettings;
use App\Filament\Pages\FooterSettings;
use App\Filament\Pages\HomepageSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فیلدهای رسانه‌ایِ CMS (Homepage/About/Footer/Members) حالا MediaPickerInput هستند که مقدارشان
 * یک رشته‌ی disk_path است. این تست‌ها بی‌رگرسیون‌بودنِ mount/save صفحات و عبورِ سالمِ آن رشته از
 * فرم به SiteSetting را می‌سنجند — یعنی جایگزینیِ ویجت، شکلِ داده و ذخیره‌سازی را نشکسته.
 */
class CmsUploadWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_still_saves_normally_without_an_upload(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        Livewire::actingAs($owner)
            ->test(AboutPageSettings::class)
            ->set('data.en.hero_name', 'Ehsan Dibazar')
            ->call('save')
            ->assertHasNoErrors();

        // منطقِ mount/save و normalizeUpload بعد از سیم‌کشی دست‌نخورده کار می‌کند
        $this->assertSame('Ehsan Dibazar', SiteSetting::get('about.en.hero_name'));
    }

    public function test_footer_and_homepage_pages_still_load_and_save_after_wiring(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        // هر دو صفحه باید بدونِ خطا mount و save شوند — یعنی سیم‌کشیِ saveUploadedFileUsing
        // چرخه‌ی موجودِ mount/save‌شان را نشکسته
        Livewire::actingAs($owner)->test(FooterSettings::class)->call('save')->assertHasNoErrors();
        Livewire::actingAs($owner)->test(HomepageSettings::class)->call('save')->assertHasNoErrors();
    }

    // فاز ۲: فیلدهای تصویرِ CMS حالا MediaPickerInput هستند که مقدارشان یک رشته‌ی disk_path است
    // (نه آرایه‌ی FileUpload). این تست‌ها تأیید می‌کنند همان مقدارِ رشته‌ای از پنجره‌ی انتخابِ رسانه
    // بی‌کم‌وکاست از mount/save عبور کرده و در SiteSetting می‌نشیند — یعنی سیم‌کشیِ جدید backward-compatible است.
    public function test_homepage_media_picker_image_value_persists_to_site_setting(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        Livewire::actingAs($owner)
            ->test(HomepageSettings::class)
            ->set('data.en.hero1_image', 'homepage/hero/picked.jpg')
            ->set('data.en.app_image', 'homepage/app-shot.png')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('homepage/hero/picked.jpg', SiteSetting::get('home.en.hero1_image'));
        $this->assertSame('homepage/app-shot.png', SiteSetting::get('home.en.app_image'));
    }

    public function test_about_and_footer_media_picker_image_values_persist(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        Livewire::actingAs($owner)
            ->test(AboutPageSettings::class)
            ->set('data.en.hero_image', 'about/hero/picked.jpg')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('about/hero/picked.jpg', SiteSetting::get('about.en.hero_image'));

        Livewire::actingAs($owner)
            ->test(FooterSettings::class)
            ->set('data.en.logo', 'footer/logo.png')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('footer/logo.png', SiteSetting::get('footer.en.logo'));
    }

    // فاز ۳: فیلدهای رسانه‌ایِ درونِ Repeater هم MediaPickerInput هستند. این تست تأیید می‌کند
    // یک عکس/ویدئوی عضو (رشته‌ی disk_path داخلِ آیتمِ ریپیتر) از چرخه‌ی mount→save سالم عبور
    // می‌کند و همان مسیر در JSONِ members باقی می‌ماند — همان شکلی که تمپلیت عمومی می‌خواند.
    public function test_repeater_embedded_media_picker_values_round_trip_through_save(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        SiteSetting::set('home.en.members', json_encode([
            ['name' => 'Ali', 'photo' => 'homepage/members/ali.jpg', 'video_embed' => '', 'video_file' => 'homepage/members/ali.mp4'],
        ]), 'homepage');

        Livewire::actingAs($owner)
            ->test(HomepageSettings::class)
            ->call('save')
            ->assertHasNoErrors();

        $members = array_values(json_decode(SiteSetting::get('home.en.members'), true));
        $this->assertSame('homepage/members/ali.jpg', $members[0]['photo']);
        $this->assertSame('homepage/members/ali.mp4', $members[0]['video_file']);
    }
}
