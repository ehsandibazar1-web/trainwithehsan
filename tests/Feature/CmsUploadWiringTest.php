<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPageSettings;
use App\Filament\Pages\FooterSettings;
use App\Filament\Pages\HomepageSettings;
use App\Filament\Support\MediaLibraryUploads;
use App\Models\Media;
use App\Models\SiteSetting;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فاز ۴: فیلدهای تصویرِ CMS (Homepage/About/Footer/Members) حالا از MediaProcessor عبور می‌کنند.
 * این تست‌ها سازوکارِ مشترک (MediaLibraryUploads::callback) و بی‌رگرسیون‌بودنِ ذخیره‌ی صفحات را
 * می‌سنجند؛ خودِ سیم‌کشیِ هر فیلد با خواندنِ ->saveUploadedFileUsing(MediaLibraryUploads::callback())
 * تأیید می‌شود.
 */
class CmsUploadWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_upload_callback_routes_a_cms_upload_through_the_dam(): void
    {
        Storage::fake('public');

        // دقیقاً همان مؤلفه‌ای که فیلدهای About/Footer/Homepage می‌سازند
        $component = FileUpload::make('img')->disk('public')->directory('about/hero');

        $path = (MediaLibraryUploads::callback())($component, UploadedFile::fake()->image('hero.jpg', 800, 600));

        // مقدارِ برگشتی همان رشته‌ی disk_path است (شکلِ ذخیره‌شده عوض نمی‌شود)
        $this->assertStringStartsWith('about/hero/', $path);

        // ولی حالا یک ردیفِ واقعیِ کتابخانه‌ی رسانه با مشتقاتِ WebP ساخته شده
        $media = Media::where('disk_path', $path)->first();
        $this->assertNotNull($media);
        $this->assertSame('image', $media->type);
        $this->assertNotNull($media->webp_path);
        Storage::disk('public')->assertExists($media->disk_path);
        Storage::disk('public')->assertExists($media->webp_path);
    }

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
}
