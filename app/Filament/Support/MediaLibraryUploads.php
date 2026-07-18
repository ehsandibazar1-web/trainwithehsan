<?php

namespace App\Filament\Support;

use App\Models\Media;
use App\Services\Media\MediaProcessor;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
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
    /**
     * اکشنِ «Choose from Media Library» کنارِ فیلدِ تصویر شاخص — اجازه می‌دهد به‌جای آپلودِ فایلِ
     * تازه، یک تصویرِ موجودِ DAM را انتخاب کنید. با انتخاب، حالتِ فیلدِ image_path روی disk_path
     * همان رسانه ست می‌شود؛ چون یک مسیرِ از-پیش-ذخیره‌شده است، هیچ آپلود/رکوردِ تکراری ساخته
     * نمی‌شود و همان فایلِ موجود دوباره‌استفاده می‌شود.
     */
    public static function pickFromLibraryAction(): Action
    {
        return Action::make('pickFromLibrary')
            ->label('Choose from Media Library')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('Choose an image from the Media Library')
            ->modalSubmitActionLabel('Use this image')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('media_id')
                    ->label('Image')
                    ->required()
                    ->searchable()
                    ->allowHtml()
                    ->helperText('Search by filename. Only images already in the Media Library are shown.')
                    ->getSearchResultsUsing(fn (string $search): array => Media::query()
                        ->where('type', 'image')
                        ->where('original_name', 'like', '%'.$search.'%')
                        ->latest()
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Media $media): array => [$media->id => self::mediaOptionLabel($media)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($media = Media::find($value)) ? self::mediaOptionLabel($media) : null),
            ])
            ->action(function (array $data, Set $set): void {
                $media = Media::find($data['media_id'] ?? null);

                if ($media) {
                    // مسیرِ رشته‌ایِ رسانه‌ی موجود را در حالتِ FileUpload می‌گذاریم؛ FileUpload آن را
                    // مثلِ یک فایلِ از-پیش-موجود نمایش می‌دهد و در ذخیره همان مسیر ثبت می‌شود
                    $set('image_path', $media->disk_path);
                }
            });
    }

    protected static function mediaOptionLabel(Media $media): string
    {
        $thumb = e($media->thumbnail_url);
        $name = e($media->original_name);

        return "<span style=\"display:inline-flex;align-items:center;gap:.5rem\"><img src=\"{$thumb}\" style=\"width:32px;height:32px;object-fit:cover;border-radius:4px\" alt=\"\">{$name}</span>";
    }

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
