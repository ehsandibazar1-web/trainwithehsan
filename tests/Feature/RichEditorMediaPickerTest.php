<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\RichContent\MediaLibraryRichContentPlugin;
use App\Models\Article;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * فاز ۷: دکمه‌ی Media Library درونِ RichEditor. منطقِ قابل-تستِ هسته (resolveImage/imageNode)،
 * سازگاری با sanitize، ردگیریِ استفاده‌ی تصویرِ درون‌متنی، و بی‌رگرسیون‌بودنِ فرمِ مقاله سنجیده
 * می‌شود. تعاملِ واقعیِ دکمه/مودال در مرورگر — طبقِ روالِ همین پروژه برای UIهای Livewire — دستی
 * تأیید می‌شود؛ اینجا مطمئن می‌شویم پلاگین فرم را نمی‌شکند و درج/ثبت درست کار می‌کند.
 */
class RichEditorMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_registers_a_media_library_tool_action_and_toolbar_button(): void
    {
        $plugin = MediaLibraryRichContentPlugin::make('articles/inline');

        $this->assertSame('mediaLibrary', $plugin->getEditorTools()[0]->getName());
        $this->assertSame('mediaLibrary', $plugin->getEditorActions()[0]->getName());
        $this->assertSame(['mediaLibrary'], $plugin->getEnabledToolbarButtons());
        // بدونِ افزونه‌ی TipTap سفارشی — پس هیچ JS build ای لازم نیست
        $this->assertSame([], $plugin->getTipTapJsExtensions());
    }

    public function test_resolve_image_stores_a_new_upload_through_the_dam_with_derivatives(): void
    {
        Storage::fake('public');

        $resolved = MediaLibraryRichContentPlugin::resolveImage(
            ['upload' => UploadedFile::fake()->image('inline.jpg', 800, 600), 'alt' => 'A fighter'],
            'articles/inline',
        );

        $this->assertNotNull($resolved);
        $this->assertSame('A fighter', $resolved['alt']);

        // یک ردیفِ واقعیِ DAM با WebP ساخته شده و src به فایلِ اصلی (نه WebP) اشاره می‌کند
        $media = Media::where('type', 'image')->firstOrFail();
        $this->assertNotNull($media->webp_path);
        $this->assertStringStartsWith('articles/inline/', $media->disk_path);
        $this->assertStringContainsString($media->disk_path, $resolved['src']);
    }

    public function test_resolve_image_uses_an_existing_media_selection(): void
    {
        $media = Media::create([
            'original_name' => 'hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/hero.jpg',
            'url' => 'http://localhost/storage/articles/hero.jpg', 'type' => 'image', 'alt_text' => 'Existing alt',
        ]);

        $resolved = MediaLibraryRichContentPlugin::resolveImage(['media_id' => $media->id], 'articles/inline');

        $this->assertSame($media->url, $resolved['src']);
        $this->assertSame('Existing alt', $resolved['alt']); // alt خالی → از خودِ رسانه پر می‌شود
    }

    public function test_resolve_image_returns_null_when_nothing_is_chosen(): void
    {
        $this->assertNull(MediaLibraryRichContentPlugin::resolveImage(['alt' => 'x'], 'articles/inline'));
    }

    public function test_inserted_image_keeps_the_media_usage_tracked(): void
    {
        Storage::fake('public');

        $resolved = MediaLibraryRichContentPlugin::resolveImage(
            ['upload' => UploadedFile::fake()->image('inline.jpg', 800, 600)],
            'articles/inline',
        );
        $media = Media::where('type', 'image')->firstOrFail();

        // متنِ مقاله شاملِ همان src می‌شود — چون src خودِ disk_path را دربردارد، MediaUsageScanner
        // آن را «در حال استفاده» می‌بیند (نه یتیم)
        Article::create([
            'locale' => 'en', 'title' => 'Uses inline', 'slug' => 'uses-inline',
            'body' => '<p><img src="'.$resolved['src'].'" alt="x"></p>',
            'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $this->assertTrue($media->isInUse());
        $this->assertFalse($media->isOrphan());
    }

    public function test_inserted_image_markup_survives_html_sanitization(): void
    {
        $node = MediaLibraryRichContentPlugin::imageNode('/storage/articles/inline/x.jpg', 'A caption');
        $this->assertSame('image', $node['type']);
        $this->assertSame('/storage/articles/inline/x.jpg', $node['attrs']['src']);

        // #73: بدنه با Str::sanitizeHtml رندر می‌شود — <img src alt> باید بماند
        $clean = Str::sanitizeHtml('<p><img src="/storage/articles/inline/x.jpg" alt="A caption"></p>');
        $this->assertStringContainsString('<img', $clean);
        $this->assertStringContainsString('/storage/articles/inline/x.jpg', $clean);
    }

    public function test_article_create_form_still_mounts_with_the_plugin_attached(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        // اگر پلاگین/ابزار/اکشن اشتباه سیم‌کشی شده بود، mount شدنِ فرم خطا می‌داد
        Livewire::actingAs($owner)->test(CreateArticle::class)->assertSuccessful();
    }
}
