<?php

namespace App\Filament\Support;

use App\Models\Media;
use App\Services\Media\MediaProcessor;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * اکشنِ hint برای فیلدِ تصویر شاخص — ALT متنِ رکوردِ Media متناظر را همان‌جا در ویرایشگر
     * (بدونِ رفتن به کتابخانه‌ی رسانه) ویرایش می‌کند. ALT روی رکوردِ Media می‌نشیند، نه روی
     * Article/Page — پس مستقیماً همان‌جا نوشته می‌شود. فقط وقتی دیده می‌شود که رکورد یک تصویر
     * شاخصِ ذخیره‌شده با یک ردیفِ Media داشته باشد (در صفحه‌ی Create هنوز رکوردی نیست).
     */
    public static function altHintAction(): Action
    {
        return Action::make('editFeaturedImageAlt')
            ->label('Edit ALT text')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (?Model $record): bool => filled($record?->image_path) && Media::forRecord($record) !== null)
            ->fillForm(fn (?Model $record): array => ['alt_text' => Media::forRecord($record)?->alt_text])
            ->schema([
                TextInput::make('alt_text')
                    ->label('ALT text (accessibility & image SEO)')
                    ->helperText('Describes the featured image for screen readers and search engines. Saved to the Media Library entry.')
                    ->maxLength(1000),
            ])
            ->action(function (array $data, ?Model $record): void {
                Media::forRecord($record)?->update([
                    'alt_text' => filled($data['alt_text'] ?? null) ? $data['alt_text'] : null,
                ]);
            });
    }
}
