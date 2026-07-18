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

        $filename = (string) Str::ulid().'.'.$extension;
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
            default => null,
        };
    }

    private function generateImageDerivatives(Media $media): void
    {
        $disk = Storage::disk($media->disk);
        $absolutePath = $disk->path($media->disk_path);

        $dimensions = @getimagesize($absolutePath);
        if ($dimensions === false) {
            // فایلی که MIME‌اش تصویر تشخیص داده شده ولی محتوایش خوانده نمی‌شود (مثلا JPEG
            // بریده/خراب یا کدگذاریِ پشتیبانی‌نشده) — رکورد بدون مشتقات باقی می‌ماند و فایل اصلی
            // هرگز حذف نمی‌شود. پیش از این این شاخه کاملا خاموش بود؛ حالا گزارش می‌شود تا در لاگ
            // دیده شود و Media::processingFailed() هم آن را در پنل نشان می‌دهد.
            report(new \RuntimeException(
                "MediaProcessor: could not read image dimensions for media #{$media->id} ({$media->disk_path}) — file may be corrupt or an unsupported encoding; stored without derivatives."
            ));

            return;
        }
        [$width, $height] = $dimensions;

        // ابعاد مستقل از WebP معلوم است — همیشه ثبتش می‌کنیم، حتی اگر تولیدِ WebP روی این هاست
        // شکست بخورد (تا Media Library باز هم اندازه/ابعاد را درست نشان دهد).
        $media->update(['width' => $width, 'height' => $height]);

        try {
            $manager = ImageManager::gd();
        } catch (Throwable $e) {
            // GD در دسترس نیست/راه‌اندازی نمی‌شود — یک نقصِ سطحِ سرور، نه فایلِ خراب؛ باز هم
            // به‌جای شکستِ خاموش گزارش می‌شود تا قابلِ تشخیص باشد چرا هیچ تصویری مشتق نمی‌گیرد.
            report($e);

            return;
        }

        // اگر GDِ این سرور از WebP پشتیبانی نکند (یا imagewebp در disable_functions باشد — همان
        // نوع محدودیتی که روی این هاست exec/symlink را هم بسته)، تولیدِ WebP ممکن نیست. به‌جای
        // اینکه استثنا بالا برود و کلِ آپلود «ناموفق» نشان داده شود، این‌جا شفاف گزارش می‌شود و
        // فایلِ اصلی سالم می‌ماند؛ سایت از همان فایلِ اصل سِرو می‌کند (Article/Page::optimized_image_url
        // به فایلِ اصلی fallback می‌کند) و Media::processingFailed() موضوع را در پنل نشان می‌دهد.
        if (! function_exists('imagewebp')) {
            report(new \RuntimeException(
                "MediaProcessor: this server's GD build has no WebP support (imagewebp() is unavailable/disabled) — media #{$media->id} ({$media->disk_path}) was stored without a WebP derivative. Ask the host to enable WebP support in the PHP GD extension."
            ));

            return;
        }

        // خودِ تولید/ذخیره‌ی مشتقات در try/catch است تا هر شکستی (کدگذاری، حافظه، مجوزِ دیسک)
        // آپلود را نشکند و فایلِ اصلی و رکورد سالم بمانند — پیش از این این بخش محافظت‌نشده بود و
        // یک شکستِ WebP کلِ آپلود را «ناموفق» نشان می‌داد.
        try {
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
        } catch (Throwable $e) {
            report($e);
        }
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
