<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Livewire\MediaPicker;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * پنجره‌ی انتخابِ رسانه‌ی یکپارچه (فاز ۱) — کامپوننتِ سراسریِ MediaPicker و فیلدِ MediaPickerInput.
 * منطقِ کتابخانه (پوشه/آپلود/فیلتر/جزئیات) در InteractsWithMediaLibrary است و با
 * MediaLibraryTest پوشش داده شده؛ اینجا فقط رفتارِ خاصِ پیکر (باز/بسته، فیلترِ نوع، فقط-تصویر،
 * انتخاب-و-بازگشت، و backward-compatibility مقدارِ فیلد) سنجیده می‌شود.
 */
class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function image(string $path = 'media/library/a.jpg'): Media
    {
        return Media::create([
            'original_name' => 'a.jpg', 'disk' => 'public', 'disk_path' => $path,
            'url' => 'http://localhost/storage/'.$path, 'type' => 'image',
        ]);
    }

    public function test_it_starts_closed_and_opens_for_a_target_field(): void
    {
        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->assertSet('isOpen', false)
            ->call('openFor', 'data.image_path', true, 'articles')
            ->assertSet('isOpen', true)
            ->assertSet('target', 'data.image_path')
            ->assertSet('onlyImages', true)
            ->assertSet('uploadDirectory', 'articles')
            // فیلدِ فقط-تصویر پیکر را از ابتدا روی فیلترِ تصویر قفل می‌کند
            ->assertSet('typeFilter', 'image');
    }

    public function test_only_images_mode_locks_the_type_filter(): void
    {
        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->call('openFor', 'data.image_path', true)
            ->call('setTypeFilter', 'video')
            // در حالتِ فقط-تصویر، تلاش برای عوض کردنِ فیلتر نادیده گرفته می‌شود
            ->assertSet('typeFilter', 'image');
    }

    public function test_general_mode_allows_switching_type_filters(): void
    {
        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->call('openFor', 'data.attachment', false)
            ->assertSet('typeFilter', 'all')
            ->call('setTypeFilter', 'document')
            ->assertSet('typeFilter', 'document');
    }

    public function test_choosing_returns_the_disk_path_to_the_field_and_closes(): void
    {
        $media = $this->image();

        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->call('openFor', 'data.image_path', true, 'articles')
            ->call('chooseAndReturn', $media->id)
            // پنجره بسته می‌شود؛ نتیجه با یک window CustomEvent (media-picker-selected) به فیلد می‌رود
            ->assertSet('isOpen', false)
            ->assertSet('target', null);
    }

    public function test_an_image_only_field_refuses_a_non_image_selection(): void
    {
        $video = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'media/library/clip.mp4',
            'url' => 'http://x/clip.mp4', 'type' => 'video',
        ]);

        Livewire::actingAs($this->owner())
            ->test(MediaPicker::class)
            ->call('openFor', 'data.image_path', true)
            ->call('chooseAndReturn', $video->id)
            // انتخابِ ویدئو در یک فیلدِ فقط-تصویر رد می‌شود — پنجره باز می‌ماند
            ->assertSet('isOpen', true);
    }

    public function test_type_filter_maps_documents_audio_and_archives(): void
    {
        $doc = Media::create(['original_name' => 'n.pdf', 'disk' => 'public', 'disk_path' => 'm/n.pdf', 'url' => 'x', 'type' => 'document', 'mime_type' => 'application/pdf']);
        $audio = Media::create(['original_name' => 's.mp3', 'disk' => 'public', 'disk_path' => 'm/s.mp3', 'url' => 'x', 'type' => 'audio', 'mime_type' => 'audio/mpeg']);
        $zip = Media::create(['original_name' => 'b.zip', 'disk' => 'public', 'disk_path' => 'm/b.zip', 'url' => 'x', 'type' => 'other', 'mime_type' => 'application/zip']);
        $img = $this->image();
        $owner = $this->owner();

        $idsFor = fn (string $filter) => Livewire::actingAs($owner)->test(MediaPicker::class)
            ->call('openFor', 'data.attachment', false)
            ->set('typeFilter', $filter)
            ->instance()->mediaItems->pluck('id');

        $this->assertEqualsCanonicalizing([$doc->id], $idsFor('document')->all());
        $this->assertEqualsCanonicalizing([$audio->id], $idsFor('audio')->all());
        $this->assertEqualsCanonicalizing([$zip->id], $idsFor('archive')->all());
        // «Other» اختصاصیِ پیکر = فقط طبقه‌بندی‌نشده (نه سند/صوت/آرشیو)
        $this->assertFalse($idsFor('other_only')->contains($doc->id));
        $this->assertFalse($idsFor('other_only')->contains($zip->id));
    }

    public function test_extended_search_matches_alt_caption_and_description(): void
    {
        $byAlt = Media::create(['original_name' => 'x1.jpg', 'disk' => 'public', 'disk_path' => 'm/x1.jpg', 'url' => 'x', 'type' => 'image', 'alt_text' => 'grappling closeup']);
        $byCaption = Media::create(['original_name' => 'x2.jpg', 'disk' => 'public', 'disk_path' => 'm/x2.jpg', 'url' => 'x', 'type' => 'image', 'caption' => 'grappling seminar']);
        $unrelated = Media::create(['original_name' => 'x3.jpg', 'disk' => 'public', 'disk_path' => 'm/x3.jpg', 'url' => 'x', 'type' => 'image', 'alt_text' => 'sunset']);

        $ids = Livewire::actingAs($this->owner())->test(MediaPicker::class)
            ->call('openFor', 'data.attachment', false)
            ->set('search', 'grappling')
            ->instance()->mediaItems->pluck('id');

        $this->assertTrue($ids->contains($byAlt->id));
        $this->assertTrue($ids->contains($byCaption->id));
        $this->assertFalse($ids->contains($unrelated->id));
    }

    public function test_media_picker_input_stores_a_plain_disk_path_string(): void
    {
        // MediaPickerInput یک فیلدِ رشته‌ای ساده است — همان شکلِ مقداری که FileUpload قبلی داشت
        $field = MediaPickerInput::make('image_path')->onlyImages()->uploadDirectory('articles');

        $this->assertTrue($field->isOnlyImages());
        $this->assertSame('articles', $field->getUploadDirectory());
    }

    public function test_page_featured_image_is_stored_as_a_disk_path_via_the_picker_field(): void
    {
        // PageForm هم مثل ArticleForm از MediaPickerInput استفاده می‌کند — مقدار همان disk_path است
        $media = $this->image('pages/hero.jpg');

        Livewire::actingAs($this->owner())
            ->test(CreatePage::class)
            ->fillForm([
                'locale' => 'en', 'title' => 'Picked page', 'slug' => 'picked-page',
                'body' => '<p>Body</p>', 'status' => 'draft',
                'image_path' => $media->disk_path,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('pages', ['slug' => 'picked-page', 'image_path' => 'pages/hero.jpg']);
        $this->assertSame(1, Media::where('disk_path', 'pages/hero.jpg')->count());
    }

    public function test_article_edit_page_renders_the_media_picker_field_for_an_existing_image(): void
    {
        // با یک تصویرِ شاخصِ موجود، فیلد باید تامبنیلِ رسانه‌ی متناظر را رندر کند
        // (getSelectedMedia درونِ کانتینرِ واقعیِ فرم اجرا می‌شود) — بی‌خطا و backward-compatible
        $this->image('articles/hero.jpg');
        $article = Article::create([
            'locale' => 'en', 'title' => 'With hero', 'slug' => 'with-hero',
            'body' => '<p>x</p>', 'image_path' => 'articles/hero.jpg', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        Livewire::actingAs($this->owner())
            ->test(EditArticle::class, ['record' => $article->id])
            ->assertOk()
            ->assertFormSet(['image_path' => 'articles/hero.jpg']);
    }
}
