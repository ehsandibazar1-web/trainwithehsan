<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Livewire\MediaPicker;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * ویرایشِ ALT تصویرِ شاخص و caption/description از خودِ پنجره‌ی انتخابِ رسانه (که در فاز یکپارچه‌سازی
 * جای اکشن‌های کمکیِ قدیمیِ کنارِ فیلد را گرفت)، و ویرایشِ دستیِ caption/description در پنلِ جزئیات.
 */
class MediaMetadataUxTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_choosing_an_existing_image_sets_it_as_the_featured_image_on_save(): void
    {
        // یک تصویرِ موجودِ DAM که پنجره‌ی انتخابِ رسانه‌ی یکپارچه (MediaPickerInput) آن را انتخاب می‌کند
        $media = Media::create([
            'original_name' => 'existing.jpg', 'disk' => 'public', 'disk_path' => 'media/library/existing.jpg',
            'url' => 'http://localhost/storage/media/library/existing.jpg', 'type' => 'image',
        ]);

        // پیکر با media-picker-selected مقدارِ فیلد را روی disk_pathِ رسانه‌ی انتخاب‌شده ست می‌کند —
        // یعنی همان رشته‌ی مسیر که فیلدِ قبلی هم ذخیره می‌کرد (backward-compatible)
        Livewire::actingAs($this->owner())
            ->test(CreateArticle::class)
            ->fillForm([
                'locale' => 'en', 'title' => 'Picked hero', 'slug' => 'picked-hero',
                'body' => '<p>Body</p>', 'author_name' => 'Ehsan', 'status' => 'draft',
                'image_path' => $media->disk_path,
            ])
            ->call('create')
            ->assertHasNoErrors();

        // بدونِ آپلودِ تازه و بدونِ رکوردِ Media تکراری — همان فایلِ موجود دوباره‌استفاده شده
        $this->assertDatabaseHas('articles', ['slug' => 'picked-hero', 'image_path' => 'media/library/existing.jpg']);
        $this->assertSame(1, Media::where('disk_path', 'media/library/existing.jpg')->count());
    }

    public function test_featured_image_alt_is_edited_inside_the_media_picker(): void
    {
        // پس از یکپارچه‌سازی، ALTِ تصویرِ شاخص در خودِ پنجره‌ی انتخابِ رسانه ویرایش می‌شود، نه با یک
        // اکشنِ hint کنارِ فیلد — همان جریانِ saveAltText که کتابخانه‌ی رسانه هم دارد
        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => 'http://localhost/storage/articles/hero.jpg', 'type' => 'image',
        ]);

        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->call('selectMedia', $media->id)
            ->call('saveAltText', 'A grappling exchange');

        $this->assertSame('A grappling exchange', $media->refresh()->alt_text);
    }

    public function test_media_library_saves_caption_and_description_manually(): void
    {
        $media = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'media/clip.mp4',
            'url' => 'http://x/clip.mp4', 'type' => 'video',
        ]);

        Livewire::actingAs($this->owner())
            ->test(MediaLibrary::class)
            ->set('selectedMediaId', $media->id)
            ->call('saveCaption', '  A short caption  ')
            ->call('saveDescription', 'A longer description of the clip.');

        $media->refresh();
        $this->assertSame('A short caption', $media->caption); // trim شده
        $this->assertSame('A longer description of the clip.', $media->description);
    }

    public function test_saving_a_blank_caption_clears_it(): void
    {
        $media = Media::create([
            'original_name' => 'a.jpg', 'disk' => 'public', 'disk_path' => 'media/a.jpg',
            'url' => 'http://x/a.jpg', 'type' => 'image', 'caption' => 'old',
        ]);

        Livewire::actingAs($this->owner())
            ->test(MediaLibrary::class)
            ->set('selectedMediaId', $media->id)
            ->call('saveCaption', '   ');

        $this->assertNull($media->refresh()->caption);
    }
}
