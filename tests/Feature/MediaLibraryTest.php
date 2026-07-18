<?php

namespace Tests\Feature;

use App\Filament\Pages\MediaLibrary;
use App\Models\Article;
use App\Models\Media;
use App\Models\MediaFolder;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Media\MediaProcessor;
use App\Services\Media\MediaUsageScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
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

        // از فاز ۱ (طبقه‌بندیِ عمومیِ رسانه) یک PDF به‌جای 'other' حالا 'document' می‌شود —
        // به هر حال یک فایلِ غیرتصویری است و هیچ مشتقی نمی‌گیرد؛ فقط دسته‌بندی‌اش دقیق‌تر شد
        $this->assertSame('document', $media->type);
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

    public function test_processing_failed_is_true_for_an_image_row_with_no_webp_and_false_once_processed(): void
    {
        // یک ردیفِ type=image بدون webp_path — یعنی پردازشِ زمانِ آپلود شکست خورده
        $failed = Media::create([
            'original_name' => 'corrupt.jpg', 'disk' => 'public', 'disk_path' => 'media/corrupt.jpg',
            'url' => 'http://x/corrupt.jpg', 'type' => 'image',
        ]);
        $this->assertTrue($failed->processingFailed());

        // یک تصویرِ واقعی که از MediaProcessor عبور کرده باید webp داشته باشد و شکست‌خورده نباشد
        Storage::fake('public');
        $processed = $this->processor()->store($this->fakeImage(800, 600), 'media/library', 'public');
        $this->assertNotNull($processed->webp_path);
        $this->assertFalse($processed->processingFailed());
    }

    public function test_processing_failed_is_false_for_non_image_files(): void
    {
        // فایلِ غیرتصویری (type=other) هرگز مشتق نمی‌گیرد، پس نبودِ webp «شکست» نیست
        $other = Media::create([
            'original_name' => 'notes.pdf', 'disk' => 'public', 'disk_path' => 'media/notes.pdf',
            'url' => 'http://x/notes.pdf', 'type' => 'other',
        ]);
        $this->assertFalse($other->processingFailed());
    }

    public function test_processing_failed_state_does_not_leak_into_warnings(): void
    {
        // مشاهده‌پذیریِ «شکستِ پردازش» عمداً از warnings() جداست تا AgentAuditService/scoreCard
        // را تحتِ تأثیر نگذارد — یک تصویرِ سالمِ فقط-بدونِ-webp نباید هیچ warning ای بسازد
        $failed = Media::create([
            'original_name' => 'corrupt.jpg', 'disk' => 'public', 'disk_path' => 'media/corrupt2.jpg',
            'url' => 'http://x/corrupt2.jpg', 'type' => 'image', 'width' => 800, 'height' => 600,
            'alt_text' => 'has alt', 'webp_path' => null,
        ]);

        $this->assertTrue($failed->processingFailed());
        $this->assertSame([], $failed->warnings());
    }

    public function test_resolve_type_classifies_real_mimes_into_the_media_taxonomy(): void
    {
        $p = $this->processor();

        $this->assertSame('image', $p->resolveType('image/png'));
        $this->assertSame('image', $p->resolveType('image/webp'));
        $this->assertSame('video', $p->resolveType('video/mp4'));
        $this->assertSame('video', $p->resolveType('video/quicktime'));
        $this->assertSame('audio', $p->resolveType('audio/mpeg'));
        $this->assertSame('document', $p->resolveType('application/pdf'));
        $this->assertSame('document', $p->resolveType('text/plain'));
        // zip و هر MIME ناشناخته/خالی → 'other' (پیش‌فرضِ امن)
        $this->assertSame('other', $p->resolveType('application/zip'));
        $this->assertSame('other', $p->resolveType('application/x-unknown'));
        $this->assertSame('other', $p->resolveType(null));
    }

    public function test_storing_a_real_image_still_resolves_to_type_image(): void
    {
        Storage::fake('public');

        $media = $this->processor()->store($this->fakeImage(800, 600), 'media/library', 'public');

        $this->assertSame('image', $media->type);
        $this->assertNotNull($media->webp_path);
    }

    public function test_type_filters_partition_image_video_and_other_with_nothing_vanishing(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $image = Media::create([
            'original_name' => 'a.jpg', 'disk' => 'public', 'disk_path' => 'media/a.jpg',
            'url' => 'http://x/a.jpg', 'type' => 'image',
        ]);
        $video = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'media/clip.mp4',
            'url' => 'http://x/clip.mp4', 'type' => 'video',
        ]);
        $doc = Media::create([
            'original_name' => 'notes.pdf', 'disk' => 'public', 'disk_path' => 'media/notes.pdf',
            'url' => 'http://x/notes.pdf', 'type' => 'document',
        ]);
        $other = Media::create([
            'original_name' => 'bundle.zip', 'disk' => 'public', 'disk_path' => 'media/bundle.zip',
            'url' => 'http://x/bundle.zip', 'type' => 'other',
        ]);

        $idsFor = fn (string $filter) => Livewire::actingAs($owner)->test(MediaLibrary::class)
            ->set('typeFilter', $filter)
            ->instance()->mediaItems->pluck('id');

        // Images = فقط تصویر
        $images = $idsFor('image');
        $this->assertTrue($images->contains($image->id));
        $this->assertFalse($images->contains($video->id));

        // Videos = فقط ویدئو (فیلترِ اختصاصیِ فاز ۵)
        $videos = $idsFor('video');
        $this->assertTrue($videos->contains($video->id));
        $this->assertFalse($videos->contains($image->id));
        $this->assertFalse($videos->contains($doc->id));

        // Other files = نه تصویر و نه ویدئو (سند/زیپ/…) — هیچ فایلی از هیچ فیلتری ناپدید نمی‌شود
        $others = $idsFor('other');
        $this->assertFalse($others->contains($image->id));
        $this->assertFalse($others->contains($video->id));
        $this->assertTrue($others->contains($doc->id));
        $this->assertTrue($others->contains($other->id));
    }

    public function test_is_orphan_flags_unused_system_attached_files_but_not_manual_uploads_or_in_use_files(): void
    {
        // استفاده‌نشده + در articles/ → یتیم (تصویر شاخصِ عوض‌شده یا ایمپورتِ rollback‌شده)
        $orphanArticle = Media::create([
            'original_name' => 'old-hero.jpg', 'disk' => 'public', 'disk_path' => 'articles/old-hero.jpg',
            'url' => 'http://x/old-hero.jpg', 'type' => 'image',
        ]);
        $this->assertTrue($orphanArticle->isOrphan());

        // استفاده‌نشده + هیروی تولیدی → یتیم (regenerate شده)
        $orphanHero = Media::create([
            'original_name' => 'hero.png', 'disk' => 'public', 'disk_path' => 'ai-generated/hero.png',
            'url' => 'http://x/hero.png', 'type' => 'image',
        ]);
        $this->assertTrue($orphanHero->isOrphan());

        // آپلودِ دستیِ استفاده‌نشده‌ی media/library → یتیم نیست (عمداً منتظرِ استفاده)
        $manual = Media::create([
            'original_name' => 'staged.jpg', 'disk' => 'public', 'disk_path' => 'media/library/staged.jpg',
            'url' => 'http://x/staged.jpg', 'type' => 'image',
        ]);
        $this->assertFalse($manual->isOrphan());

        // در articles/ ولی در حال استفاده → یتیم نیست
        $inUse = Media::create([
            'original_name' => 'current.jpg', 'disk' => 'public', 'disk_path' => 'articles/current.jpg',
            'url' => 'http://x/current.jpg', 'type' => 'image',
        ]);
        Article::create([
            'locale' => 'en', 'title' => 'Uses current', 'slug' => 'uses-current',
            'body' => '<p>x</p>', 'image_path' => 'articles/current.jpg', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);
        $this->assertFalse($inUse->isOrphan());
    }

    public function test_orphaned_filter_shows_only_unused_system_attached_files(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $orphan = Media::create([
            'original_name' => 'orphan.jpg', 'disk' => 'public', 'disk_path' => 'articles/orphan.jpg',
            'url' => 'http://x/orphan.jpg', 'type' => 'image',
        ]);
        $manualUnused = Media::create([
            'original_name' => 'staged.jpg', 'disk' => 'public', 'disk_path' => 'media/library/staged.jpg',
            'url' => 'http://x/staged.jpg', 'type' => 'image',
        ]);
        $inUse = Media::create([
            'original_name' => 'current.jpg', 'disk' => 'public', 'disk_path' => 'articles/current.jpg',
            'url' => 'http://x/current.jpg', 'type' => 'image',
        ]);
        Article::create([
            'locale' => 'en', 'title' => 'Uses current', 'slug' => 'uses-current',
            'body' => '<p>x</p>', 'image_path' => 'articles/current.jpg', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $ids = Livewire::actingAs($owner)->test(MediaLibrary::class)
            ->set('onlyOrphaned', true)
            ->instance()->mediaItems->pluck('id');

        $this->assertTrue($ids->contains($orphan->id));
        $this->assertFalse($ids->contains($manualUnused->id));
        $this->assertFalse($ids->contains($inUse->id));
    }

    public function test_video_media_is_usage_tracked_when_referenced_from_settings(): void
    {
        // یک ویدئوی DAM-managed (type=video) که مسیرش در SiteSetting نشسته — دقیقاً همان چیزی که
        // HomepageSettings::save() بعد از عبور از MediaProcessor ذخیره می‌کند
        $video = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'homepage/videos/clip.mp4',
            'url' => 'http://x/clip.mp4', 'type' => 'video',
        ]);
        SiteSetting::set('home.en.video1_file', 'homepage/videos/clip.mp4');

        // پس ویدئوها هم مثل تصویرها ردگیریِ استفاده می‌شوند و حذفشان محافظت‌شده است
        $this->assertTrue($video->isInUse());
        $this->assertNotEmpty($video->usages());
    }

    public function test_upload_size_policy_keeps_images_at_15mb_and_allows_other_media_up_to_128mb(): void
    {
        $mb = 1024 * 1024;

        // تصویر: سقف ۱۵MB
        $this->assertFalse(MediaLibrary::isOverTypeLimit('image', 15 * $mb));
        $this->assertTrue(MediaLibrary::isOverTypeLimit('image', 16 * $mb));

        // ویدئو/صوت/سند/سایر: سقف ۱۲۸MB — یک ویدئوی ۱۰۰MB مجاز، ۲۰MB قطعاً مجاز
        $this->assertFalse(MediaLibrary::isOverTypeLimit('video', 100 * $mb));
        $this->assertFalse(MediaLibrary::isOverTypeLimit('video', 20 * $mb));
        $this->assertFalse(MediaLibrary::isOverTypeLimit('document', 20 * $mb));
        $this->assertFalse(MediaLibrary::isOverTypeLimit('audio', 20 * $mb));
        $this->assertTrue(MediaLibrary::isOverTypeLimit('video', 129 * $mb));
    }

    public function test_regenerate_rebuilds_the_webp_and_reports_success(): void
    {
        Storage::fake('public');
        $media = $this->processor()->store($this->fakeImage(800, 600), 'media/library', 'public');

        // شبیه‌سازیِ رکوردی که WebP ندارد (مثلا آپلودِ پیش از رفعِ باگ) و بازتولیدش
        $media->update(['webp_path' => null, 'thumbnail_path' => null, 'responsive_paths' => null]);

        $report = $this->processor()->regenerate($media->fresh());

        $this->assertNull($report['error']);
        $this->assertTrue($report['webp_created']);
        $this->assertTrue($report['webp_exists_on_disk']);
        $this->assertNotNull($report['webp_path']);
        // و روی خودِ رکورد هم ذخیره شده
        $this->assertNotNull($media->fresh()->webp_path);
        Storage::disk('public')->assertExists($media->fresh()->webp_path);
    }

    public function test_regenerate_reports_a_clear_error_when_the_original_is_missing(): void
    {
        Storage::fake('public');
        $media = Media::create([
            'original_name' => 'gone.jpg', 'disk' => 'public', 'disk_path' => 'media/library/gone.jpg',
            'url' => 'http://x/gone.jpg', 'type' => 'image',
        ]);

        $report = $this->processor()->regenerate($media);

        $this->assertFalse($report['webp_created']);
        $this->assertStringContainsString('missing on disk', $report['error']);
    }

    public function test_regenerate_reports_a_clear_error_for_a_non_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('media/library/clip.mp4', 'x');
        $media = Media::create([
            'original_name' => 'clip.mp4', 'disk' => 'public', 'disk_path' => 'media/library/clip.mp4',
            'url' => 'http://x/clip.mp4', 'type' => 'video',
        ]);

        $report = $this->processor()->regenerate($media);

        $this->assertFalse($report['webp_created']);
        $this->assertStringContainsString('not an image', strtolower($report['error']));
    }

    public function test_media_library_regenerate_action_generates_the_webp(): void
    {
        Storage::fake('public');
        $media = $this->processor()->store($this->fakeImage(800, 600), 'media/library', 'public');
        $media->update(['webp_path' => null]);

        Livewire::actingAs(User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']))
            ->test(MediaLibrary::class)
            ->set('selectedMediaId', $media->id)
            ->call('regenerateDerivatives', $media->id)
            ->assertHasNoErrors();

        $this->assertNotNull($media->fresh()->webp_path);
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
