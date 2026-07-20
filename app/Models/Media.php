<?php

namespace App\Models;

use App\Services\Media\MediaUsageScanner;
use App\Services\Media\VideoMetadataService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'original_name', 'disk', 'disk_path', 'url', 'type', 'mime_type', 'size',
        'folder_id', 'alt_text', 'caption', 'description', 'width', 'height', 'duration_seconds', 'webp_path', 'thumbnail_path', 'responsive_paths',
    ];

    protected $casts = [
        'responsive_paths' => 'array',
    ];

    // آستانه‌های هشدار — بر اساس بخش «Image Optimization Rules» و «Core Web Vitals Rules» در CLAUDE.md
    private const LARGE_FILE_BYTES = 500 * 1024;

    private const OVERSIZED_DIMENSION_PX = 2000;

    private const TOO_SMALL_DIMENSION_PX = 200;

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function getThumbnailUrlAttribute(): string
    {
        return $this->thumbnail_path
            ? Storage::disk($this->disk)->url($this->thumbnail_path)
            : $this->url;
    }

    public function getWebpUrlAttribute(): ?string
    {
        return $this->webp_path ? Storage::disk($this->disk)->url($this->webp_path) : null;
    }

    // مدتِ ویدئو به شکلِ ISO-8601 (PT#H#M#S) برای VideoObject.duration — null اگر ثبت نشده باشد
    public function getDurationIso8601Attribute(): ?string
    {
        return VideoMetadataService::toIso8601(
            $this->duration_seconds !== null ? (int) $this->duration_seconds : null
        );
    }

    // نگاشت عرض → آدرس، مرتب از کوچک به بزرگ — برای srcset
    public function getResponsiveUrlsAttribute(): array
    {
        $paths = collect($this->responsive_paths ?? [])->sortKeys();

        return $paths->mapWithKeys(fn ($path, $width) => [(int) $width => Storage::disk($this->disk)->url($path)])->all();
    }

    public static function humanBytes(?int $bytes): string
    {
        if (! $bytes) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $bytes;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
    }

    public function getHumanSizeAttribute(): string
    {
        return self::humanBytes($this->size);
    }

    // حجمِ فایلِ WebP روی دیسک (بایت) — null اگر WebP ساخته نشده یا فایلش نیست
    public function getWebpSizeAttribute(): ?int
    {
        if (! $this->webp_path) {
            return null;
        }

        try {
            return Storage::disk($this->disk)->exists($this->webp_path)
                ? Storage::disk($this->disk)->size($this->webp_path)
                : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getWebpHumanSizeAttribute(): string
    {
        return self::humanBytes($this->webp_size);
    }

    // درصدِ صرفه‌جویی نسبت به فایلِ اصلی — مثلا 74 یعنی WebP ۷۴٪ کوچک‌تر است. null اگر یکی از
    // دو اندازه معلوم نباشد. می‌تواند منفی هم باشد (اگر WebP از اصل بزرگ‌تر شود، برای تصاویرِ
    // خیلی کوچکِ از-پیش-فشرده) — عمداً clamp نمی‌شود تا واقعیت را نشان دهد.
    public function getWebpSavingsPercentAttribute(): ?int
    {
        $webp = $this->webp_size;

        if (! $this->size || ! $webp) {
            return null;
        }

        return (int) round((1 - $webp / $this->size) * 100);
    }

    // هشدارهای کیفیت — برای نشان دادن نشان هشدار روی هر آیتم کتابخانه‌ی رسانه
    public function warnings(): array
    {
        $warnings = [];

        if ($this->type === 'image' && blank($this->alt_text)) {
            $warnings[] = 'Missing ALT text — hurts accessibility and image search visibility.';
        }

        if ($this->size && $this->size > self::LARGE_FILE_BYTES) {
            $warnings[] = 'Large file ('.$this->human_size.') — consider compressing before using it on the site.';
        }

        if ($this->type === 'image' && $this->width && $this->height) {
            if ($this->width > self::OVERSIZED_DIMENSION_PX || $this->height > self::OVERSIZED_DIMENSION_PX) {
                $warnings[] = "Oversized dimensions ({$this->width}×{$this->height}px) — larger than any placement on the site needs, hurts load time.";
            } elseif ($this->width < self::TOO_SMALL_DIMENSION_PX || $this->height < self::TOO_SMALL_DIMENSION_PX) {
                $warnings[] = "Very small dimensions ({$this->width}×{$this->height}px) — may look pixelated as a featured/hero image.";
            }
        }

        return $warnings;
    }

    // تصویری که ثبت شده ولی هیچ نسخه‌ی بهینه‌ای (WebP) برایش ساخته نشده — یعنی پردازشِ زمانِ
    // آپلود (getimagesize/GD در MediaProcessor::generateDerivatives) شکست خورده (فایل خراب یا
    // کدگذاریِ پشتیبانی‌نشده). چون تولیدِ مشتقات هم‌زمان با store()/adopt()/replace() و به‌صورت
    // همگام اجرا می‌شود، نبودِ webp_path برای یک ردیفِ type=image به‌طور قابل‌اتکا یعنی «شکستِ
    // پردازش»، نه «هنوز پردازش‌نشده».
    //
    // این عمداً از warnings() جداست: warnings() را هم AgentAuditService (دسته‌ی image_optimization)
    // و هم ContentReviewService::scoreCard() مصرف می‌کنند؛ افزودنِ این سیگنال به آن‌جا یک یافته‌ی
    // «بهینه‌سازی تصویر» و تغییرِ امتیازِ سلامت می‌ساخت. این فقط یک نشانگرِ مشاهده‌پذیریِ کتابخانه‌ی
    // رسانه است و جای دیگری مصرف نمی‌شود.
    public function processingFailed(): bool
    {
        return $this->type === 'image' && blank($this->webp_path);
    }

    // کجاها استفاده شده — مقاله‌ها، صفحات، تنظیمات سایت (بر اساس تطبیق مسیر فایل)
    public function usages(): array
    {
        return app(MediaUsageScanner::class)->scan($this);
    }

    public function isInUse(): bool
    {
        return count($this->usages()) > 0;
    }

    // دایرکتوری‌هایی که سیستم خودش یک تصویرِ «قرار-است-ارجاع-شود» در آن‌ها می‌سازد: تصویر شاخصِ
    // مقاله (articles/ — شاملِ articles/imported/ برای ایمپورت)، تصویر شاخصِ صفحه (pages/)، و
    // هیروی تولیدشده با هوش مصنوعی (ai-generated/). این‌ها را MediaProcessor::store() از این مسیرها
    // می‌نویسد؛ نگاه کنید به ArticleForm/PageForm (saveUploadedFileUsing)، GenerateHeroImage، و
    // ArticleImportService::downloadImage(). عمدا شاملِ media/library/ (آپلودِ دستیِ ادمین) نیست.
    // فاز ۴ که فیلدهای CMS (homepage/about/footer) هم DAM-managed شوند، دایرکتوری‌هایشان اینجا اضافه می‌شود.
    private const SYSTEM_ATTACHED_DIRECTORIES = ['articles/', 'pages/', 'ai-generated/'];

    // آیا این فایل در یکی از دایرکتوری‌هایی است که سیستم تصویرِ قابل-ارجاع می‌سازد؟ محاسبه‌ی محض
    // روی disk_path — هیچ کوئری‌ای نمی‌زند (برخلافِ isInUse)، پس در حلقه‌ی رندرِ گرید امن است.
    public function isInSystemAttachedDirectory(): bool
    {
        foreach (self::SYSTEM_ATTACHED_DIRECTORIES as $prefix) {
            if (str_starts_with((string) $this->disk_path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // «یتیم»: فایلی که سیستم به‌عنوان یک تصویرِ قابل-ارجاع ساخته ولی الان هیچ‌چیز ارجاعش نمی‌دهد —
    // یعنی تصویر شاخص عوض شده، هیرو دوباره تولید شده، یا یک ایمپورت rollback شده. زیرمجموعه‌ی
    // «استفاده‌نشده» است، ولی دقیق‌تر: آپلودهای دستیِ استفاده‌نشده‌ی media/library را (که عمداً
    // منتظرِ استفاده‌اند) کنار می‌گذارد. طبق تصمیمِ کاربر هرگز خودکار حذف نمی‌شود — فقط دیده می‌شود.
    public function isOrphan(): bool
    {
        return $this->isInSystemAttachedDirectory() && ! $this->isInUse();
    }

    // رکورد Media متناظر با تصویر شاخص یک Article/Page — با تطبیق disk_path (نه کلید خارجی، طبق
    // «Image Optimization Rules» در CLAUDE.md)؛ هم AiAssistantPanel هم ProcessAiChatMessage همین
    // را صدا می‌زنند تا این جست‌وجوی سه‌خطی دو بار پیاده‌سازی نشود
    public static function forRecord(Model $record): ?self
    {
        if (blank($record->image_path)) {
            return null;
        }

        return self::where('disk_path', $record->image_path)->first();
    }

    // URLِ بهینه‌ی یک تصویرِ SiteSetting-محور (هیرو/درباره/دوره‌ها/تامبنیل‌ها در صفحه‌ی اصلی): WebPِ
    // مشتق اگر ردیفِ Media با همین disk_path موجود و WebP دارد، وگرنه همان فایلِ اصلی — دقیقاً همان
    // الگوی optimized_image_urlِ Article/Page (Section 21)، فقط برای مسیرِ خام به‌جای رکورد. کشِ
    // درون-درخواستی تا چند فراخوانی در یک رندرِ صفحه، چند کوئریِ تکراری نزند.
    /** @var array<string, string> */
    protected static array $optimizedUrlCache = [];

    public static function optimizedUrl(?string $diskPath): ?string
    {
        if (blank($diskPath)) {
            return null;
        }

        return self::$optimizedUrlCache[$diskPath] ??=
            (self::where('disk_path', $diskPath)->first()?->webp_url
                ?: asset('storage/'.ltrim($diskPath, '/')));
    }

    // جهت معکوسِ forRecord() — کدام Article/Page این تصویر را به‌عنوان تصویر شاخص استفاده می‌کند
    // (اگر اصلا کسی استفاده کند). برای App\Services\AiAgent\AgentAuditService لازم است تا بفهمد
    // یک یافته‌ی «ALT گمشده» روی یک Media، آیا واقعا رفع‌پذیر است (فیلد alt_text فقط روی تصویر
    // شاخصِ یک رکورد کار می‌کند، نه هر استفاده‌ی دلخواهی — نگاه کنید به Media::forRecord() و
    // App\Services\AiAssistant\ActionRegistry).
    public function ownerRecord(): ?Model
    {
        return Article::where('image_path', $this->disk_path)->first()
            ?? Page::where('image_path', $this->disk_path)->first();
    }
}
