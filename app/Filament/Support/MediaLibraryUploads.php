<?php

namespace App\Filament\Support;

use App\Services\Media\MediaProcessor;
use Closure;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Http\UploadedFile;

/**
 * بستنی که به FileUpload::saveUploadedFileUsing() داده می‌شود تا هر آپلود به‌جای ذخیره‌ی خامِ
 * پیش‌فرضِ Filament، از App\Services\Media\MediaProcessor عبور کند: ثبت در کتابخانه‌ی رسانه
 * (جدول media) + تولیدِ WebP/تامبنیل/سایزهای واکنش‌گرا + قابلِ ردگیریِ استفاده و حذف‌محافظت‌شده.
 *
 * دقیقاً همان الگوی ArticleForm/PageForm است، اینجا یک‌بار استخراج شده چون HomepageSettings/
 * AboutPageSettings/FooterSettings ده‌ها فیلد با همین نیاز دارند — «انتزاعِ موجه با چند
 * مصرف‌کننده‌ی واقعی»، نه پیش‌بینانه. مقدارِ برگشتی همان رشته‌ی disk_path است که این فیلدها از
 * قبل هم ذخیره می‌کردند، پس شکلِ مقدارِ ذخیره‌شده در SiteSetting/رکورد هیچ تغییری نمی‌کند —
 * فقط از این پس فایل DAM-managed است.
 */
class MediaLibraryUploads
{
    // پارامترِ فایل عمداً UploadedFile (والد) است نه TemporaryUploadedFile: در زمانِ اجرا
    // Filament یک TemporaryUploadedFile می‌دهد (که خودش یک UploadedFile است)، و این امضای
    // بازتر اجازه می‌دهد همین بست مستقیم و بدونِ ساختِ فایلِ موقتِ Livewire تست شود.
    public static function callback(): Closure
    {
        return fn (BaseFileUpload $component, UploadedFile $file) => app(MediaProcessor::class)
            ->store($file, $component->getDirectory(), $component->getDiskName())
            ->disk_path;
    }
}
