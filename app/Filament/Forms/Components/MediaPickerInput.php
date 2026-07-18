<?php

namespace App\Filament\Forms\Components;

use App\Models\Media;
use Closure;
use Filament\Forms\Components\Field;

/**
 * فیلدِ یکپارچه‌ی انتخابِ رسانه — تنها مؤلفه‌ای که برای انتخابِ فایل در کلِ CMS استفاده می‌شود.
 * هیچ کشوی جست‌وجوپذیر/دکمه‌ی کمکیِ کوچکی نباید باقی بماند؛ همه‌ی فیلدهای رسانه‌ای (تصویر شاخص،
 * هیرو، بنر، اسلایدر، بخش‌های صفحه‌ی اصلی، اعضا، گالری، لوگو، پس‌زمینه‌ی CTA و هر فیلدِ رسانه‌ای
 * آینده) این را می‌سازند.
 *
 * مقدارِ ذخیره‌شده همان رشته‌ی disk_path است — عیناً همان چیزی که FileUpload های فعلی ذخیره
 * می‌کردند — پس هر خواننده‌ی موجود (BlogController، تمپلیت‌های عمومی، SeoController، …) و هر
 * ردیفِ محتوای موجود بدونِ تغییر کار می‌کند. فیلد فقط ویجت را عوض می‌کند، نه شکلِ داده را.
 *
 * باز کردن: با یک window CustomEvent «open-media-picker» پنجره‌ی سراسریِ App\Livewire\MediaPicker
 * را باز می‌کند؛ انتخاب از طریقِ «media-picker-selected» برمی‌گردد (نگاه کنید به آن کامپوننت).
 */
class MediaPickerInput extends Field
{
    protected string $view = 'filament.forms.components.media-picker-input';

    // فقط تصویر؟ فیلدهای Featured/Hero/Banner این را true می‌کنند؛ پیکر خودش به تصویر فیلتر می‌شود
    protected bool|Closure $onlyImages = false;

    // پوشه‌ای که آپلودِ تازه از درونِ پیکر در آن بنشیند — پیش‌فرض media/library، ولی مثلا تصویرِ
    // شاخصِ مقاله می‌تواند 'articles' بدهد تا ردگیریِ «یتیم» (isInSystemAttachedDirectory) دست‌نخورده بماند
    protected string|Closure $uploadDirectory = 'media/library';

    public function onlyImages(bool|Closure $condition = true): static
    {
        $this->onlyImages = $condition;

        return $this;
    }

    public function uploadDirectory(string|Closure $directory): static
    {
        $this->uploadDirectory = $directory;

        return $this;
    }

    public function isOnlyImages(): bool
    {
        return (bool) $this->evaluate($this->onlyImages);
    }

    public function getUploadDirectory(): string
    {
        return (string) $this->evaluate($this->uploadDirectory);
    }

    // رکوردِ Media متناظر با مقدارِ فعلی (disk_path) — برای نمایشِ تامبنیل/نامِ فایل در ویجت.
    // اگر مقدار به فایلی اشاره کند که ردیفِ Media ندارد (تصویرِ پیش از DAM)، null برمی‌گردد و
    // ویجت به نمایشِ خودِ مسیر برمی‌گردد — چیزی نمی‌شکند.
    public function getSelectedMedia(): ?Media
    {
        $state = $this->getState();

        if (blank($state)) {
            return null;
        }

        return Media::where('disk_path', $state)->first();
    }
}
