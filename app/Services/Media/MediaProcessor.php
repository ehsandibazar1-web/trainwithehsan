<?php

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * ذخیره‌سازی فایل اصلی + تولید خودکار مشتقات (WebP / تامبنیل / اندازه‌های واکنش‌گرا).
 * فایل اصلی هرگز حذف یا جایگزین نمی‌شود مگر در replace() — طبق درخواست «Keep original files».
 */
class MediaProcessor
{
    // از بزرگ‌ترین به کوچک‌ترین؛ هرکدام فقط اگر از عرض تصویر اصلی کوچک‌تر باشد ساخته می‌شود
    private const RESPONSIVE_WIDTHS = [1600, 1200, 800, 480];

    private const THUMBNAIL_WIDTH = 320;

    // فهرست سفیدِ نوع فایل‌های مجاز برای آپلود — پسوندِ ذخیره‌شده روی دیسک همیشه از این نگاشت
    // انتخاب می‌شود (بر اساس نوعِ MIME واقعیِ محتوا، نه پسوندِ نام فایلِ کلاینت که قابلِ جعل
    // است)، تا هیچ‌وقت فایلی با پسوندِ اجراشدنی (مثل .php) روی دیسک ذخیره نشود
    private const SAFE_MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/bmp' => 'bmp',
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/plain' => 'txt',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'application/zip' => 'zip',
    ];

    // طبقه‌بندیِ نوعِ رسانه بر اساس MIME واقعی — ستونِ `type` روی رکورد Media از این نگاشت پر
    // می‌شود. عمداً از فهرستِ سفیدِ بالا جداست: allowlist می‌گوید «آیا اجازه‌ی ذخیره دارد و با
    // چه پسوندی»، این می‌گوید «چه نوعی است». هر MIME ای که اینجا نباشد (مثل application/zip یا
    // هر نوعِ آینده‌ی هنوز-طبقه‌بندی‌نشده) به‌صورت امن 'other' می‌شود. افزودنِ یک فرمتِ جدید در
    // آینده = یک ورودی در allowlist بالا + یک ورودی اینجا؛ نیازی به بازطراحیِ این کلاس نیست.
    private const MIME_CATEGORIES = [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime'],
        'audio' => ['audio/mpeg', 'audio/wav'],
        'document' => [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
        ],
    ];

    public function store(UploadedFile $file, string $directory, string $disk = 'public', ?int $folderId = null): Media
    {
        // getMimeType() محتوای واقعیِ فایل را می‌خواند (نه هدر Content-Type ارسالیِ کلاینت)
        $mimeType = $file->getMimeType();
        $extension = $this->assertSafeExtension($mimeType);

        // اسمِ فایلِ توصیفی (SEOِ عکس) از نامِ اصلی: muay-thai.png به‌جای ULIDِ گنگ. اگر نام غیرِلاتین
        // باشد و slug خالی درآید (مثلاً تماماً فارسی)، به ULID برمی‌گردیم تا هرگز اسمِ خالی نسازیم.
        // یکتایی با پسوندِ عددیِ افزایشی تضمین می‌شود تا هیچ فایلِ موجودی بازنویسی نشود.
        $filename = $this->descriptiveFilename($file->getClientOriginalName(), $extension, $directory, $disk);
        $storedPath = $file->storeAs($directory, $filename, $disk);

        Storage::disk($disk)->setVisibility($storedPath, 'public');

        $media = Media::create([
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'disk_path' => $storedPath,
            'url' => Storage::disk($disk)->url($storedPath),
            'type' => $this->resolveType($mimeType),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'folder_id' => $folderId,
        ]);

        $this->generateDerivatives($media);

        return $media;
    }

    /**
     * ثبت فایلی که از قبل روی دیسک هست (بدون کپی/جابه‌جایی) در کتابخانه‌ی رسانه — برای بازیابی رسانه‌های قدیمی.
     */
    public function adopt(string $path, string $disk = 'public', ?int $folderId = null): Media
    {
        $mimeType = Storage::disk($disk)->mimeType($path) ?: null;

        $media = Media::create([
            'original_name' => basename($path),
            'disk' => $disk,
            'disk_path' => $path,
            'url' => Storage::disk($disk)->url($path),
            'type' => $this->resolveType($mimeType),
            'mime_type' => $mimeType,
            'size' => Storage::disk($disk)->size($path),
            'folder_id' => $folderId,
        ]);

        $this->generateDerivatives($media);

        return $media;
    }

    /**
     * جایگزینی محتوای فایل — مسیر اصلی (disk_path) دست‌نخورده می‌ماند تا لینک‌های موجود در
     * مقاله‌ها/صفحات/تنظیمات سایت که این مسیر را به‌صورت رشته‌ی خام ذخیره کرده‌اند نشکنند.
     */
    public function replace(Media $media, UploadedFile $file): Media
    {
        // فقط اعتبارسنجی می‌شود (پرتاب استثنا در صورت نوعِ ناامن) — چون disk_path و پسوندش
        // عوض نمی‌شود، اینجا صرفاً از ذخیره‌ی محتوای ناامن جلوگیری می‌کند
        $this->assertSafeExtension($file->getMimeType());

        Storage::disk($media->disk)->put($media->disk_path, file_get_contents($file->getRealPath()));
        Storage::disk($media->disk)->setVisibility($media->disk_path, 'public');

        $this->deleteDerivatives($media);

        $mimeType = $file->getMimeType();

        $media->update([
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'size' => $file->getSize(),
            'type' => $this->resolveType($mimeType),
            'width' => null,
            'height' => null,
            'webp_path' => null,
            'thumbnail_path' => null,
            'responsive_paths' => null,
            'duration_seconds' => null,
        ]);

        $this->generateDerivatives($media->fresh());

        return $media->fresh();
    }

    public function delete(Media $media): void
    {
        $this->deleteDerivatives($media);
        Storage::disk($media->disk)->delete($media->disk_path);
        $media->delete();
    }

    // نقطه‌ی گسترش‌پذیریِ خط‌لوله: امروز فقط تصویرها مشتق می‌گیرند، ولی این dispatch عمداً باز
    // است تا افزودنِ پردازش برای یک نوعِ جدید در آینده (مثلا poster/thumbnail برای ویدئو،
    // waveform برای صوت) صرفاً یک شاخه‌ی match و یک متدِ generateXDerivatives باشد — نه
    // بازنویسیِ این متد یا شکستنِ رفتارِ تصویرها.
    private function generateDerivatives(Media $media): void
    {
        match ($media->type) {
            'image' => $this->generateImageDerivatives($media),
            'video' => $this->probeVideoDuration($media),
            default => null,
        };
    }

    // ویدئو مشتقِ تصویری نمی‌گیرد، ولی مدتِ زمانش را (بدونِ وابستگی) از هدرِ فایل می‌خوانیم و ذخیره
    // می‌کنیم تا VideoObject/سایت‌مپ بعداً بدونِ پردازشِ درخواستی از آن استفاده کنند. مثلِ مشتقاتِ
    // تصویر: هر خطا گرفته و گزارش می‌شود، فایل و رکورد سالم می‌مانند (duration اختیاری است).
    private function probeVideoDuration(Media $media): void
    {
        try {
            $absolutePath = Storage::disk($media->disk)->path($media->disk_path);
            $seconds = (new VideoMetadataService)->durationSeconds($absolutePath);

            if ($seconds !== null && $seconds !== (int) $media->duration_seconds) {
                $media->update(['duration_seconds' => $seconds]);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    // مسیرِ آپلود: هرگز نباید به‌خاطرِ شکستِ مشتقات بشکند — هر خطا گرفته و گزارش می‌شود، فایلِ
    // اصلی و رکورد سالم می‌مانند، و Media::processingFailed() موضوع را در پنل نشان می‌دهد.
    private function generateImageDerivatives(Media $media): void
    {
        try {
            $this->buildImageDerivatives($media);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * کارِ واقعیِ ساختِ مشتقات — برخلافِ generateImageDerivatives، در صورتِ شکست استثنا پرتاب
     * می‌کند (نه گزارشِ خاموش) تا regenerate() بتواند دلیلِ دقیق را به ادمین نشان دهد.
     * ابعاد مستقل از WebP ثبت می‌شود، پس حتی اگر WebP شکست بخورد اندازه/ابعاد درست می‌ماند.
     */
    private function buildImageDerivatives(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $absolutePath = $disk->path($media->disk_path);

        $dimensions = @getimagesize($absolutePath);
        if ($dimensions === false) {
            throw new \RuntimeException("could not read image dimensions ({$media->disk_path}) — the file may be corrupt or an unsupported encoding.");
        }
        [$width, $height] = $dimensions;
        $media->update(['width' => $width, 'height' => $height]);

        $manager = ImageManager::gd();

        if (! function_exists('imagewebp')) {
            throw new \RuntimeException("this server's GD build has no WebP support (imagewebp() is unavailable/disabled).");
        }

        $webpPath = $this->siblingPath($media->disk_path, '.webp');
        $manager->read($absolutePath)->toWebp(quality: 82)->save($disk->path($webpPath));
        $disk->setVisibility($webpPath, 'public');

        $thumbPath = $this->siblingPath($media->disk_path, '-thumb.webp');
        $manager->read($absolutePath)->scaleDown(width: self::THUMBNAIL_WIDTH)->toWebp(quality: 75)->save($disk->path($thumbPath));
        $disk->setVisibility($thumbPath, 'public');

        $responsivePaths = [];
        foreach (self::RESPONSIVE_WIDTHS as $targetWidth) {
            if ($targetWidth >= $width) {
                continue;
            }

            $variantPath = $this->siblingPath($media->disk_path, "-{$targetWidth}w.webp");
            $manager->read($absolutePath)->scaleDown(width: $targetWidth)->toWebp(quality: 80)->save($disk->path($variantPath));
            $disk->setVisibility($variantPath, 'public');
            $responsivePaths[$targetWidth] = $variantPath;
        }

        $media->update([
            'webp_path' => $webpPath,
            'thumbnail_path' => $thumbPath,
            'responsive_paths' => $responsivePaths ?: null,
        ]);
    }

    /**
     * تشخیص + بازتولیدِ مشتقاتِ یک رسانه‌ی موجود — پاسخِ زنده به «چرا WebP ساخته نمی‌شود؟».
     * مشتقاتِ قبلی را پاک و از نو می‌سازد و یک گزارشِ دقیق برمی‌گرداند (فایلِ اصلی هست؟ WebP
     * ساخته شد؟ روی دیسک هست؟ مسیرش ذخیره شد؟ اگر نه، دلیلِ دقیقِ خطا).
     *
     * @return array{type: string, original_exists: bool, webp_created: bool, webp_path: ?string, webp_exists_on_disk: bool, error: ?string}
     */
    public function regenerate(Media $media): array
    {
        $disk = Storage::disk($media->disk);

        $report = [
            'type' => $media->type,
            'original_exists' => $disk->exists($media->disk_path),
            'webp_created' => false,
            'webp_path' => null,
            'webp_exists_on_disk' => false,
            'error' => null,
        ];

        if (! $report['original_exists']) {
            $report['error'] = "The original file is missing on disk ({$media->disk_path}).";

            return $report;
        }

        if ($media->type !== 'image') {
            $report['error'] = "This is a {$media->type} file, not an image — no WebP derivative is generated for it.";

            return $report;
        }

        $this->deleteDerivatives($media);
        $media->update(['webp_path' => null, 'thumbnail_path' => null, 'responsive_paths' => null]);

        try {
            $this->buildImageDerivatives($media->fresh());
        } catch (Throwable $e) {
            $report['error'] = $e->getMessage();

            return $report;
        }

        $fresh = $media->fresh();
        $report['webp_path'] = $fresh->webp_path;
        $report['webp_created'] = filled($fresh->webp_path);
        $report['webp_exists_on_disk'] = filled($fresh->webp_path) && $disk->exists($fresh->webp_path);

        return $report;
    }

    private function deleteDerivatives(Media $media): void
    {
        $disk = Storage::disk($media->disk);

        foreach (array_filter([$media->webp_path, $media->thumbnail_path]) as $path) {
            $disk->delete($path);
        }

        foreach ((array) $media->responsive_paths as $path) {
            $disk->delete($path);
        }
    }

    private function siblingPath(string $originalPath, string $suffix): string
    {
        $directory = dirname($originalPath);
        $base = pathinfo($originalPath, PATHINFO_FILENAME);

        return ($directory === '.' ? '' : $directory.'/').$base.$suffix;
    }

    // نوعِ رسانه‌ی یک MIME واقعی: image | video | audio | document | other. عمومی است تا
    // مصرف‌کننده‌های دیگر (مثلا فیلترِ کتابخانه‌ی رسانه) هم بتوانند از همین منطقِ واحد استفاده
    // کنند، به‌جای بازتولیدِ یک نگاشتِ موازی.
    // نامِ توصیفیِ یکتا از نامِ اصلیِ فایل: slug + پسوندِ عددی در صورتِ تصادم. slugِ خالی → ULID.
    private function descriptiveFilename(string $originalName, string $extension, string $directory, string $disk): string
    {
        $slug = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        if ($slug === '') {
            $slug = (string) Str::ulid();
        }
        $slug = Str::limit($slug, 80, '');

        $candidate = $slug.'.'.$extension;
        $i = 1;
        while (Storage::disk($disk)->exists($directory.'/'.$candidate)) {
            $i++;
            $candidate = $slug.'-'.$i.'.'.$extension;
        }

        return $candidate;
    }

    public function resolveType(?string $mimeType): string
    {
        foreach (self::MIME_CATEGORIES as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes, true)) {
                return $type;
            }
        }

        return 'other';
    }

    private function assertSafeExtension(?string $mimeType): string
    {
        $extension = self::SAFE_MIME_EXTENSIONS[$mimeType] ?? null;

        if (! $extension) {
            throw new \RuntimeException('Unsupported file type'.($mimeType ? " ($mimeType)" : '').'.');
        }

        return $extension;
    }
}
