<?php

namespace App\Livewire;

use App\Filament\Concerns\InteractsWithMediaLibrary;
use App\Models\Media;
use Filament\Notifications\Notification;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

/**
 * پنجره‌ی انتخابِ رسانه‌ی یکپارچه (Unified Media Picker) — یک کامپوننتِ Livewire که در چرومِ پنل
 * یک‌بار سراسری mount می‌شود (نگاه کنید به AdminPanelProvider render hook) و هر فیلدِ رسانه‌ای در
 * کلِ CMS همین را باز می‌کند. هیچ پیکرِ دوم/کشویی‌ای نباید باقی بماند.
 *
 * منطقِ کتابخانه (پوشه/آپلود/فیلتر/جزئیات/حذف/بازتولید) دقیقاً همان trait ای است که صفحه‌ی
 * MediaLibrary استفاده می‌کند (App\Filament\Concerns\InteractsWithMediaLibrary) — پس صفر
 * دوباره‌کاری. این کلاس فقط رفتارِ «مودال + انتخاب-و-بازگشت به فیلد» را رویش می‌گذارد.
 *
 * قراردادِ رویدادها (طراحی‌شده تا هر نوعِ رسانه و هر مصرف‌کننده‌ی آینده — RichEditor، فیلدهای
 * فایلِ عمومی، حتی ضمائمِ Knowledge Base — بدونِ بازطراحیِ پیکر واردش شوند):
 *   - باز شدن: فیلد یک window CustomEvent «open-media-picker» با detail={target, onlyImages,
 *     uploadDirectory} می‌فرستد؛ ریشه‌ی این کامپوننت آن را می‌گیرد و openFor() را صدا می‌زند.
 *   - انتخاب: این کامپوننت یک window CustomEvent «media-picker-selected» با detail شاملِ
 *     {target, disk_path, url, type, mime_type, original_name} می‌فرستد؛ فقط فیلدی که target اش
 *     برابر باشد به آن گوش می‌دهد و مقدارش را ست می‌کند. مقدارِ ذخیره‌شده همان رشته‌ی disk_path
 *     است — عیناً همان چیزی که فیلدهای فعلی ذخیره می‌کنند، پس کاملاً backward-compatible.
 */
class MediaPicker extends Component
{
    use InteractsWithMediaLibrary;
    use WithFileUploads;

    public bool $isOpen = false;

    // فیلدی (statePath) که پنجره را باز کرده و نتیجه باید به آن برگردد — برای RichEditor مقادیری
    // مثل «richeditor:...» هم می‌گیرد؛ این کامپوننت فقط آن را echo می‌کند و درباره‌ی شکلش قضاوت نمی‌کند
    public ?string $target = null;

    // فیلدهای تصویری (Featured/Hero/Banner…) این را true می‌فرستند تا پیکر خودکار فقط تصویر نشان دهد
    public bool $onlyImages = false;

    public string $viewMode = 'grid'; // grid | list

    // «Instant search by Filename, ALT, Caption, Description, … Extension» — پیکر برخلافِ صفحه‌ی
    // MediaLibrary (که فقط نام فایل را می‌گردد) روی همه‌ی این ستون‌ها جست‌وجو می‌کند
    protected function searchableColumns(): array
    {
        return ['original_name', 'alt_text', 'caption', 'description', 'mime_type'];
    }

    public function openFor(?string $target, bool $onlyImages = false, ?string $uploadDirectory = null): void
    {
        $this->target = $target;
        $this->onlyImages = $onlyImages;
        $this->uploadDirectory = $uploadDirectory ?: 'media/library';
        $this->isOpen = true;

        // شروعِ تمیز هر بار که باز می‌شود
        $this->selectedMediaId = null;
        $this->search = '';
        $this->currentFolderId = null;
        $this->resetPickerFilters();
        $this->typeFilter = $onlyImages ? 'image' : 'all';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->target = null;
        $this->selectedMediaId = null;
        $this->showNewFolderForm = false;
    }

    public function setViewMode(string $mode): void
    {
        $this->viewMode = in_array($mode, ['grid', 'list'], true) ? $mode : 'grid';
    }

    public function setTypeFilter(string $filter): void
    {
        // در حالتِ فقط-تصویر، فیلترِ نوع قفل روی image می‌ماند
        if ($this->onlyImages) {
            return;
        }

        $this->typeFilter = $filter;
        $this->selectedMediaId = null;
    }

    private function resetPickerFilters(): void
    {
        $this->onlyUnused = false;
        $this->onlyOrphaned = false;
        $this->onlyMissingAlt = false;
        $this->onlyLarge = false;
    }

    // «یک کلیک انتخاب می‌کند» (پیش‌نمایش) از selectMedia استفاده می‌کند؛ این «درج فوری» است —
    // دابل‌کلیک یا دکمه‌ی «Use this file». مقدار را به فیلدِ فراخوان برمی‌گرداند و پنجره را می‌بندد.
    public function chooseAndReturn(int $mediaId): void
    {
        $media = Media::find($mediaId);

        if (! $media) {
            return;
        }

        if ($this->onlyImages && $media->type !== 'image') {
            Notification::make()
                ->warning()
                ->title('This field only accepts images')
                ->body('Please choose an image file.')
                ->send();

            return;
        }

        // یک window CustomEvent قطعی می‌فرستیم (نه فقط dispatch سمتِ سرور) تا فیلد — که با Alpine
        // به window گوش می‌دهد — بی‌ابهام آن را بگیرد، مستقل از جزئیاتِ رویدادهای Livewire↔Alpine
        $payload = json_encode([
            'target' => $this->target,
            'disk_path' => $media->disk_path,
            'url' => $media->url,
            'type' => $media->type,
            'mime_type' => $media->mime_type,
            'original_name' => $media->original_name,
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);

        $this->js("window.dispatchEvent(new CustomEvent('media-picker-selected', { detail: {$payload} }))");

        $this->close();
    }

    // آیکونِ نوعِ فایل برای رسانه‌های غیرتصویری در شبکه/فهرست — نگاشتِ ساده‌ی emoji، بدونِ وابستگیِ
    // آیکون‌ست. تصاویر خودشان تامبنیل دارند و هرگز به این نمی‌رسند.
    public static function icon(Media $media): string
    {
        return match (true) {
            $media->type === 'video' => '🎬',
            $media->type === 'audio' => '🎵',
            $media->mime_type === 'application/pdf' => '📕',
            $media->mime_type === 'application/zip' => '🗜️',
            $media->type === 'document' => '📄',
            default => '📎',
        };
    }

    public function render()
    {
        return view('livewire.media-picker');
    }
}
