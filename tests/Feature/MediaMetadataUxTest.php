<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Support\MediaLibraryUploads;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فاز ۸ (پرداختِ UX): ویرایشِ ALT تصویرِ شاخص از خودِ ویرایشگر، و ویرایشِ دستیِ caption/description
 * در پنلِ جزئیاتِ کتابخانه‌ی رسانه (که تا حالا فقط با AI پر می‌شدند).
 */
class MediaMetadataUxTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_featured_image_alt_hint_action_is_wired(): void
    {
        $action = MediaLibraryUploads::altHintAction();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('editFeaturedImageAlt', $action->getName());
    }

    public function test_pick_from_library_action_is_wired(): void
    {
        $action = MediaLibraryUploads::pickFromLibraryAction();

        $this->assertInstanceOf(Action::class, $action);
        $this->assertSame('pickFromLibrary', $action->getName());
    }

    public function test_choosing_an_existing_image_sets_it_as_the_featured_image_on_save(): void
    {
        // یک تصویرِ موجودِ DAM که می‌خواهیم به‌جای آپلودِ تازه انتخابش کنیم
        $media = Media::create([
            'original_name' => 'existing.jpg', 'disk' => 'public', 'disk_path' => 'media/library/existing.jpg',
            'url' => 'http://localhost/storage/media/library/existing.jpg', 'type' => 'image',
        ]);

        Livewire::actingAs($this->owner())
            ->test(CreateArticle::class)
            ->fillForm([
                'locale' => 'en', 'title' => 'Picked hero', 'slug' => 'picked-hero',
                'body' => '<p>Body</p>', 'author_name' => 'Ehsan', 'status' => 'draft',
            ])
            ->callFormComponentAction('image_path', 'pickFromLibrary', data: ['media_id' => $media->id])
            ->call('create')
            ->assertHasNoErrors();

        // بدونِ آپلودِ تازه و بدونِ رکوردِ Media تکراری — همان فایلِ موجود دوباره‌استفاده شده
        $this->assertDatabaseHas('articles', ['slug' => 'picked-hero', 'image_path' => 'media/library/existing.jpg']);
        $this->assertSame(1, Media::where('disk_path', 'media/library/existing.jpg')->count());
    }

    public function test_featured_image_alt_action_writes_to_the_media_row_from_the_editor(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'With hero', 'slug' => 'with-hero',
            'body' => '<p>x</p>', 'image_path' => 'articles/hero.jpg', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);
        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => 'http://localhost/storage/articles/hero.jpg', 'type' => 'image',
        ]);

        Livewire::actingAs($this->owner())
            ->test(EditArticle::class, ['record' => $article->id])
            ->callFormComponentAction('image_path', 'editFeaturedImageAlt', data: ['alt_text' => 'A grappling exchange']);

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
