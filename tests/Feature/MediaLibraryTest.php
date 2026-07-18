<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Services\Media\MediaProcessor;
use App\Services\Media\MediaUsageScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function processor(): MediaProcessor
    {
        return app(MediaProcessor::class);
    }

    private function fakeImage(int $width = 1600, int $height = 900, string $extension = 'jpg'): UploadedFile
    {
        return UploadedFile::fake()->image('photo.'.$extension, $width, $height);
    }

    public function test_store_keeps_original_and_generates_webp_thumbnail_and_responsive_variants(): void
    {
        Storage::fake('public');

        $media = $this->processor()->store($this->fakeImage(1600, 900), 'media/library', 'public');

        $this->assertSame('image', $media->type);
        $this->assertSame(1600, $media->width);
        $this->assertSame(900, $media->height);

        Storage::disk('public')->assertExists($media->disk_path);
        Storage::disk('public')->assertExists($media->webp_path);
        Storage::disk('public')->assertExists($media->thumbnail_path);

        $this->assertNotEmpty($media->responsive_paths);
        foreach ($media->responsive_paths as $width => $path) {
            $this->assertLessThan(1600, (int) $width);
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_store_of_small_image_generates_no_responsive_variants(): void
    {
        Storage::fake('public');

        $media = $this->processor()->store($this->fakeImage(400, 300), 'media/library', 'public');

        $this->assertNull($media->responsive_paths);
        Storage::disk('public')->assertExists($media->webp_path);
        Storage::disk('public')->assertExists($media->thumbnail_path);
    }

    // ---------------------------------------------------------- upload restrictions (security)

    public function test_stored_filename_extension_comes_from_real_content_not_the_client_filename(): void
    {
        Storage::fake('public');

        // نام کلاینت می‌گوید jpg، ولی خودِ فایل واقعاً یک png است — پسوندِ ذخیره‌شده باید از
        // روی محتوای واقعی (png) انتخاب شود، نه از پسوندِ نامِ فایل که کلاینت ادعا کرده
        $image = UploadedFile::fake()->image('photo.png', 200, 200);
        $mismatched = new UploadedFile($image->getPathname(), 'photo.jpg', 'image/png', null, true);

        $media = $this->processor()->store($mismatched, 'media/library', 'public');

        $this->assertStringEndsWith('.png', $media->disk_path);
    }

    public function test_a_real_pdf_upload_is_accepted_as_a_non_image_document(): void
    {
        Storage::fake('public');

        $pdf = UploadedFile::fake()->createWithContent('guide.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<< >>\nendobj\ntrailer\n<< >>\n%%EOF");

        $media = $this->processor()->store($pdf, 'media/library', 'public');

        $this->assertSame('other', $media->type);
        $this->assertStringEndsWith('.pdf', $media->disk_path);
        Storage::disk('public')->assertExists($media->disk_path);
    }

    public function test_store_rejects_a_file_whose_real_content_is_not_an_allowed_type(): void
    {
        Storage::fake('public');

        // بایت‌های دلخواه که هیچ نوعِ فایلِ شناخته‌شده‌ای را تشکیل نمی‌دهند
        $unknown = UploadedFile::fake()->createWithContent('payload.bin', "\x7F\x45\x4C\x46random-binary-junk-not-a-real-format");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported file type');

        $this->processor()->store($unknown, 'media/library', 'public');
    }

    public function test_media_library_form_rule_rejects_a_php_extension_regardless_of_content(): void
    {
        // محتوای واقعی یک تصویر معتبر است، ولی پسوندِ نامِ فایل php است — قانونِ mimes: خودِ
        // لاراول این را حتی قبل از رسیدن به MediaProcessor رد می‌کند (shouldBlockPhpUpload)،
        // چون 'php' در فهرستِ مجاز (همان رشته‌ی استفاده‌شده در MediaLibrary::updatedUploads()) نیست
        $disguised = UploadedFile::fake()->image('shell.php', 10, 10);

        $validator = Validator::make(
            ['file' => $disguised],
            ['file' => ['file', 'max:15360', 'mimes:jpg,jpeg,png,webp,gif,bmp,pdf,doc,docx,xls,xlsx,txt,mp4,webm,mov,mp3,wav,zip']]
        );

        $this->assertTrue($validator->fails());
    }

    public function test_replace_keeps_the_same_disk_path_so_existing_links_do_not_break(): void
    {
        Storage::fake('public');

        $media = $this->processor()->store($this->fakeImage(1600, 900), 'media/library', 'public');
        $originalPath = $media->disk_path;
        $oldWebpPath = $media->webp_path;

        $replaced = $this->processor()->replace($media, $this->fakeImage(400, 300, 'png'));

        $this->assertSame($originalPath, $replaced->disk_path);
        $this->assertSame(400, $replaced->width);
        $this->assertSame(300, $replaced->height);
        $this->assertNull($replaced->responsive_paths);

        Storage::disk('public')->assertExists($replaced->disk_path);
        Storage::disk('public')->assertExists($replaced->webp_path);
        // مشتقات قدیمی (که دیگر معتبر نیستند، چون ابعاد عوض شده) باید پاک شده باشند
        if ($oldWebpPath !== $replaced->webp_path) {
            Storage::disk('public')->assertMissing($oldWebpPath);
        }
    }

    public function test_delete_removes_original_and_all_derivative_files(): void
    {
        Storage::fake('public');

        $media = $this->processor()->store($this->fakeImage(1600, 900), 'media/library', 'public');
        $paths = array_filter(array_merge(
            [$media->disk_path, $media->webp_path, $media->thumbnail_path],
            array_values($media->responsive_paths ?? [])
        ));
        $this->assertNotEmpty($paths);

        $this->processor()->delete($media);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
        }
    }

    public function test_media_folders_are_nested_and_only_deletable_while_empty(): void
    {
        $parent = MediaFolder::create(['name' => 'Blog']);
        $child = MediaFolder::create(['name' => '2026', 'parent_id' => $parent->id]);

        $this->assertSame('Blog / 2026', $child->fullPath());
        $this->assertTrue($child->isEmpty());
        $this->assertFalse($parent->isEmpty());

        Storage::fake('public');
        $media = $this->processor()->store($this->fakeImage(), 'media/library', 'public', $child->id);

        $this->assertFalse($child->fresh()->isEmpty());

        $media->delete();
        $this->assertTrue($child->fresh()->isEmpty());
    }

    public function test_usage_scanner_finds_article_featured_image_and_body_references(): void
    {
        $media = Media::create([
            'original_name' => 'hero.jpg',
            'disk' => 'public',
            'disk_path' => 'articles/hero.jpg',
            'url' => 'http://localhost/storage/articles/hero.jpg',
            'type' => 'image',
        ]);

        $usedAsFeatured = Article::create([
            'locale' => 'en', 'title' => 'Featured usage', 'slug' => 'featured-usage',
            'body' => '<p>no images here</p>', 'image_path' => 'articles/hero.jpg',
            'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $usedInBody = Article::create([
            'locale' => 'en', 'title' => 'Body usage', 'slug' => 'body-usage',
            'body' => '<p><img src="/storage/articles/hero.jpg"></p>',
            'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $usages = app(MediaUsageScanner::class)->scan($media);

        $this->assertCount(2, $usages);
        $labels = array_column($usages, 'label');
        $this->assertContains($usedAsFeatured->title.' (EN)', $labels);
        $this->assertContains($usedInBody->title.' (EN)', $labels);
        $this->assertTrue($media->isInUse());
    }

    public function test_usage_scanner_finds_page_and_site_setting_references(): void
    {
        $media = Media::create([
            'original_name' => 'bg.jpg', 'disk' => 'public', 'disk_path' => 'pages/bg.jpg',
            'url' => 'http://localhost/storage/pages/bg.jpg', 'type' => 'image',
        ]);

        Page::create([
            'locale' => 'en', 'title' => 'Privacy', 'slug' => 'privacy',
            'body' => '<p>x</p>', 'image_path' => 'pages/bg.jpg', 'status' => 'draft',
        ]);

        SiteSetting::set('home.en.hero1_image', 'pages/bg.jpg');

        $usages = app(MediaUsageScanner::class)->scan($media);

        $this->assertCount(2, $usages);
        $this->assertTrue($media->isInUse());
    }

    public function test_unused_media_reports_no_usages(): void
    {
        $media = Media::create([
            'original_name' => 'orphan.jpg', 'disk' => 'public', 'disk_path' => 'media/library/orphan.jpg',
            'url' => 'http://localhost/storage/media/library/orphan.jpg', 'type' => 'image',
        ]);

        $this->assertSame([], $media->usages());
        $this->assertFalse($media->isInUse());
    }

    public function test_warnings_flag_missing_alt_large_files_and_bad_dimensions(): void
    {
        $missingAlt = Media::create([
            'original_name' => 'a.jpg', 'disk' => 'public', 'disk_path' => 'a.jpg',
            'url' => 'http://x/a.jpg', 'type' => 'image', 'width' => 800, 'height' => 600,
        ]);
        $this->assertStringContainsString('Missing ALT', implode(' ', $missingAlt->warnings()));

        $withAlt = Media::create([
            'original_name' => 'b.jpg', 'disk' => 'public', 'disk_path' => 'b.jpg',
            'url' => 'http://x/b.jpg', 'type' => 'image', 'width' => 800, 'height' => 600,
            'alt_text' => 'A descriptive caption',
        ]);
        $this->assertSame([], $withAlt->warnings());

        $large = Media::create([
            'original_name' => 'c.jpg', 'disk' => 'public', 'disk_path' => 'c.jpg',
            'url' => 'http://x/c.jpg', 'type' => 'image', 'width' => 800, 'height' => 600,
            'alt_text' => 'ok', 'size' => 600 * 1024,
        ]);
        $this->assertStringContainsString('Large file', implode(' ', $large->warnings()));

        $oversized = Media::create([
            'original_name' => 'd.jpg', 'disk' => 'public', 'disk_path' => 'd.jpg',
            'url' => 'http://x/d.jpg', 'type' => 'image', 'width' => 3000, 'height' => 2000,
            'alt_text' => 'ok',
        ]);
        $this->assertStringContainsString('Oversized', implode(' ', $oversized->warnings()));

        $tiny = Media::create([
            'original_name' => 'e.jpg', 'disk' => 'public', 'disk_path' => 'e.jpg',
            'url' => 'http://x/e.jpg', 'type' => 'image', 'width' => 100, 'height' => 80,
            'alt_text' => 'ok',
        ]);
        $this->assertStringContainsString('small', implode(' ', $tiny->warnings()));
    }

    public function test_article_form_featured_image_upload_registers_a_media_library_row(): void
    {
        Storage::fake('public');

        $article = Article::create([
            'locale' => 'en', 'title' => 'With image', 'slug' => 'with-image',
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        // شبیه‌سازی همان مسیری که ArticleForm::image_path->saveUploadedFileUsing طی می‌کند
        $path = $this->processor()->store($this->fakeImage(), 'articles', 'public')->disk_path;
        $article->update(['image_path' => $path]);

        $media = Media::where('disk_path', $path)->firstOrFail();
        $this->assertTrue($media->isInUse());
        $this->assertSame('Article', $media->usages()[0]['type']);
        $this->assertSame('Featured image', $media->usages()[0]['field']);
    }
}
