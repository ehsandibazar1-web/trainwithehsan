<?php

namespace App\Filament\RichContent;

use App\Models\Media;
use App\Services\Media\MediaProcessor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * دکمه‌ی اختصاصیِ «Media Library» درونِ RichEditor — طبق تصمیمِ کاربر (فاز ۷، Option 1).
 *
 * پیش از این، تصویرهای درونِ متنِ مقاله/صفحه از سازوکارِ داخلیِ RichEditor آپلود می‌شدند و هرگز
 * وارد کتابخانه‌ی رسانه نمی‌شدند (نه ردیف Media، نه WebP، نه ردگیریِ استفاده). این پلاگین یک ابزارِ
 * تازه به نوار ابزار اضافه می‌کند که با آن می‌توان:
 *   - از میانِ تصویرهای موجودِ DAM جست‌وجو و انتخاب کرد،
 *   - یا یک تصویرِ تازه آپلود کرد که از MediaProcessor عبور می‌کند (ثبت در DAM + WebP/مشتقات)،
 * و سپس تصویرِ انتخاب‌شده به‌صورتِ یک نودِ image درونِ ویرایشگر درج می‌شود.
 *
 * ابزار دقیقاً مثلِ ابزارِ داخلیِ link ساخته شده (jsHandler همان $wire.mountAction است، بدونِ هیچ
 * افزونه‌ی TipTap سفارشی)، چون فقط یک نودِ image موجود را درج می‌کند — پس نیازی به JS build ندارد.
 *
 * سازگاری با sanitize: نودِ image به یک <img src alt> ساده تبدیل می‌شود که Str::sanitizeHtml
 * (لایه‌ی دفاعیِ #73) نگهش می‌دارد. ردگیریِ استفاده: عمداً URLِ فایلِ اصلی درج می‌شود (نه WebP)،
 * چون MediaUsageScanner متن را با disk_path تطبیق می‌دهد؛ اگر WebP درج می‌شد، تصویرِ درون‌متنی
 * به‌اشتباه «یتیم» تشخیص داده می‌شد. WebP همچنان ساخته می‌شود و برای استفاده‌ی آینده آماده است.
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
            ->modalHeading('Insert an image from the Media Library')
            ->modalSubmitActionLabel('Insert image')
            ->modalWidth(Width::Large)
            ->schema([
                Select::make('media_id')
                    ->label('Choose an existing image')
                    ->helperText('Search the Media Library by filename. Or upload a new image below.')
                    ->searchable()
                    ->allowHtml()
                    ->getSearchResultsUsing(fn (string $search): array => Media::query()
                        ->where('type', 'image')
                        ->where('original_name', 'like', '%'.$search.'%')
                        ->latest()
                        ->limit(50)
                        ->get()
                        ->mapWithKeys(fn (Media $media): array => [$media->id => static::optionLabel($media)])
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => ($media = Media::find($value)) ? static::optionLabel($media) : null),
                FileUpload::make('upload')
                    ->label('Or upload a new image')
                    ->helperText('Added to the Media Library automatically (WebP + thumbnail + responsive sizes generated).')
                    ->image()
                    ->storeFiles(false)
                    ->maxSize(15360),
                TextInput::make('alt')
                    ->label('Alt text (accessibility & image SEO)')
                    ->maxLength(1000),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component) use ($directory): void {
                $resolved = static::resolveImage($data, $directory);

                if ($resolved === null) {
                    return;
                }

                $component->runCommands(
                    [EditorCommand::make('insertContent', arguments: [static::imageNode($resolved['src'], $resolved['alt'])])],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }

    /**
     * از دیتای فرم یک {src, alt} می‌سازد: آپلودِ تازه (از MediaProcessor عبور می‌دهد) یا رسانه‌ی
     * موجود. عمداً عمومی و محض است تا مستقل از ماشینِ اکشنِ RichEditor تست شود.
     *
     * @param  array<string, mixed>  $data
     * @return array{src: string, alt: ?string}|null
     */
    public static function resolveImage(array $data, string $directory): ?array
    {
        $alt = trim((string) ($data['alt'] ?? '')) ?: null;

        if ($data['upload'] ?? null) {
            $media = app(MediaProcessor::class)->store($data['upload'], $directory, 'public');

            return ['src' => $media->url, 'alt' => $alt ?? $media->alt_text];
        }

        if (($id = $data['media_id'] ?? null) && ($media = Media::find($id))) {
            return ['src' => $media->url, 'alt' => $alt ?? $media->alt_text];
        }

        return null;
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

    protected static function optionLabel(Media $media): string
    {
        $thumb = e($media->thumbnail_url);
        $name = e($media->original_name);

        return "<span style=\"display:inline-flex;align-items:center;gap:.5rem\"><img src=\"{$thumb}\" style=\"width:32px;height:32px;object-fit:cover;border-radius:4px\" alt=\"\">{$name}</span>";
    }
}
