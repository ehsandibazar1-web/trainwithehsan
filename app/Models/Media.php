<?php

namespace App\Models;

use App\Services\Media\MediaUsageScanner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'original_name', 'disk', 'disk_path', 'url', 'type', 'mime_type', 'size',
        'folder_id', 'alt_text', 'caption', 'description', 'width', 'height', 'webp_path', 'thumbnail_path', 'responsive_paths',
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

    // نگاشت عرض → آدرس، مرتب از کوچک به بزرگ — برای srcset
    public function getResponsiveUrlsAttribute(): array
    {
        $paths = collect($this->responsive_paths ?? [])->sortKeys();

        return $paths->mapWithKeys(fn ($path, $width) => [(int) $width => Storage::disk($this->disk)->url($path)])->all();
    }

    public function getHumanSizeAttribute(): string
    {
        if (! $this->size) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->size;
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i === 0 ? 0 : 1).' '.$units[$i];
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
