<?php

namespace App\Filament\RichContent;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Models\Media;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * دکمه‌ی «Media Library» درونِ RichEditor — حالا از همان پنجره‌ی انتخابِ رسانه‌ی یکپارچه‌ی کلِ CMS
 * استفاده می‌کند (App\Livewire\MediaPicker از طریقِ فیلدِ MediaPickerInput)، نه یک Select جست‌وجوپذیر.
 * دکمه یک اکشنِ کوچک باز می‌کند که تنها محتوایش همان MediaPickerInput است + یک فیلدِ ALT؛ خودِ درج
 * از مسیرِ رسمیِ RichEditor (runCommands + editorSelection) انجام می‌شود تا انتخابِ متن حفظ شود و
 * نیازی به JS build نباشد.
 *
 * درج بر اساسِ نوعِ رسانه (طراحی‌شده تا افزودنِ پخش‌کننده‌ی ویدئو/صوت و امبدهای یوتیوب/ویمئو/اینستاگرام
 * در فازِ بعدی فقط یک شاخه‌ی تازه در insertContentFor() باشد، بی‌بازطراحیِ پیکر):
 *   - تصویر → نودِ <img src alt> (مثلِ قبل، از sanitize (#73) عبور می‌کند)
 *   - سند/زیپ/سایر → یک لینکِ دانلود (<a href> که از sanitize عبور می‌کند)
 *   - ویدئو/صوت/امبد → فعلاً به‌صورتِ لینک؛ پخش‌کننده/امبدِ واقعی در فازِ Video SEO با تغییرِ
 *     sanitizer اضافه می‌شود (عمداً اینجا انجام نشده تا این فاز فقط «پیکرِ یکپارچه» باشد).
 *
 * ردگیریِ استفاده: عمداً URLِ فایلِ اصلی درج می‌شود (نه WebP) — MediaUsageScanner متن را با
 * disk_path تطبیق می‌دهد؛ با WebP، تصویرِ درون‌متنی به‌اشتباه «یتیم» می‌شد. WebP همچنان ساخته می‌شود.
 */
class MediaLibraryRichContentPlugin implements HasToolbarButtons, RichContentPlugin
{
    public function __construct(protected string $directory = 'content-images') {}

    public static function make(string $directory = 'content-images'): static
    {
        return app(static::class, ['directory' => $directory]);
    }

    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    public function getTipTapJsExtensions(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [
            RichEditorTool::make('mediaLibrary')
                ->label('Media Library')
                ->action()
                ->icon(Heroicon::Photo),
        ];
    }

    public function getEditorActions(): array
    {
        return [$this->action()];
    }

    public function getEnabledToolbarButtons(): array
    {
        return ['mediaLibrary'];
    }

    public function getDisabledToolbarButtons(): array
    {
        return [];
    }

    protected function action(): Action
    {
        $directory = $this->directory;

        return Action::make('mediaLibrary')
            ->label('Media Library')
            ->modalHeading('Insert from the Media Library')
            ->modalSubmitActionLabel('Insert')
            ->modalWidth(Width::Large)
            ->schema([
                MediaPickerInput::make('media')
                    ->label('Media')
                    ->helperText('Pick any file from the Media Library, or upload a new one inside the picker. Images are inserted inline; documents are inserted as a download link.')
                    ->uploadDirectory($directory)
                    ->required(),
                TextInput::make('alt')
                    ->label('Alt text (images only — accessibility & image SEO)')
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $media = Media::where('disk_path', $data['media'] ?? '')->first();

                if (! $media) {
                    return;
                }

                $content = static::insertContentFor($media, $data['alt'] ?? null);

                $component->runCommands(
                    [EditorCommand::make('insertContent', arguments: [$content])],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }

    /**
     * محتوایی که بر اساسِ نوعِ رسانه در ویرایشگر درج می‌شود — نودِ image برای تصویر (آرایه‌ی TipTap)
     * یا HTMLِ لینکِ دانلود برای بقیه. عمداً عمومی و محض است تا مستقل از ماشینِ اکشن تست شود.
     *
     * @return array<string, mixed>|string
     */
    public static function insertContentFor(Media $media, ?string $alt = null): array|string
    {
        if ($media->type === 'image') {
            return static::imageNode($media->url, ($alt !== null && trim($alt) !== '') ? $alt : $media->alt_text);
        }

        // اسناد/زیپ/ویدئو/صوت/سایر: فعلاً لینکِ دانلود (تنها HTMLِ غیرتصویری که از sanitize (#73)
        // عبور می‌کند). پخش‌کننده/امبدِ واقعی در فازِ Video SEO با گشودنِ allowlistِ sanitizer می‌آید.
        return static::downloadLinkHtml($media->url, $media->original_name);
    }

    /**
     * ساختارِ نودِ image برای TipTap — که در نهایت به <img src alt> رندر می‌شود.
     *
     * @return array<string, mixed>
     */
    public static function imageNode(string $src, ?string $alt): array
    {
        return [
            'type' => 'image',
            'attrs' => [
                'src' => $src,
                'alt' => $alt,
            ],
        ];
    }

    // یک لینکِ دانلودِ ساده برای فایلِ غیرتصویری — <a href> از Str::sanitizeHtml عبور می‌کند
    // (همان تگی که Internal Linking Center هم به بدنه اضافه می‌کند)، برخلافِ یک کارتِ div-محور
    // که sanitize حذفش می‌کرد
    public static function downloadLinkHtml(string $url, string $filename): string
    {
        return '<a href="'.e($url).'" target="_blank" rel="noopener">📎 '.e($filename).'</a>';
    }
}
