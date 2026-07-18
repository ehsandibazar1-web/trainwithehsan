<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Filament\Support\MediaLibraryUploads;
use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class AboutPageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'About Page Settings';

    protected static ?string $title = 'About Page Settings';

    protected string $view = 'filament.pages.about-page-settings';

    public ?array $data = [];

    // کلیدهای متنی ساده — برای هر دو زبان یکسان تعریف می‌شوند
    private const TEXT_KEYS = [
        'hero_name', 'hero_title', 'hero_bio', 'hero_cta_text', 'hero_cta_url',
        'certs_heading', 'gallery_heading', 'timeline_heading',
        'cta_title', 'cta_description', 'cta_button_text', 'cta_button_url',
        'seo_title', 'seo_description',
    ];

    // کلیدهای فایل (عکس) — مقدارشان مسیر فایل روی دیسک public است
    private const FILE_KEYS = [
        'hero_image', 'cta_bg_image', 'seo_og_image',
    ];

    // کلیدهای ریپیتر — به‌صورت JSON ذخیره می‌شوند
    private const REPEATER_KEYS = [
        'stats', 'certificates', 'gallery', 'timeline',
    ];

    public function mount(): void
    {
        $state = [];

        foreach (['en', 'tr'] as $locale) {
            foreach (self::TEXT_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("about.$locale.$key");
            }
            foreach (self::FILE_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("about.$locale.$key");
            }
            foreach (self::REPEATER_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::getJson("about.$locale.$key");
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('English — Hero')
                    ->schema(self::heroFields('en')),
                Section::make('English — Statistics')
                    ->schema([self::statsRepeater('en')])
                    ->collapsed(),
                Section::make('English — Certificates & Credentials')
                    ->schema(self::certificatesFields('en'))
                    ->collapsed(),
                Section::make('English — About Gallery')
                    ->schema(self::galleryFields('en'))
                    ->collapsed(),
                Section::make('English — Timeline')
                    ->schema(self::timelineFields('en'))
                    ->collapsed(),
                Section::make('English — CTA Section')
                    ->schema(self::ctaFields('en'))
                    ->collapsed(),
                Section::make('English — SEO')
                    ->schema(self::seoFields('en'))
                    ->collapsed(),

                Section::make('Türkçe — Hero')
                    ->schema(self::heroFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — İstatistikler')
                    ->schema([self::statsRepeater('tr')])
                    ->collapsed(),
                Section::make('Türkçe — Sertifikalar ve Başarılar')
                    ->schema(self::certificatesFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Galeri')
                    ->schema(self::galleryFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Zaman Çizelgesi')
                    ->schema(self::timelineFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — CTA Bölümü')
                    ->schema(self::ctaFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — SEO')
                    ->schema(self::seoFields('tr'))
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    private static function heroFields(string $l): array
    {
        return [
            MediaPickerInput::make("$l.hero_image")
                ->label('Hero photo')
                ->onlyImages()
                ->uploadDirectory('about/hero')
                ->nullable(),
            TextInput::make("$l.hero_name")
                ->label('Name'),
            TextInput::make("$l.hero_title")
                ->label('Professional title')
                ->helperText('Shown under the name, e.g. "Martial arts & self-defense instructor, MSc in Sport Science..."'),
            Textarea::make("$l.hero_bio")
                ->label('Biography')
                ->rows(5),
            TextInput::make("$l.hero_cta_text")
                ->label('CTA button text (optional)')
                ->helperText('Leave both CTA fields empty to hide the button — it is optional and off by default.'),
            TextInput::make("$l.hero_cta_url")
                ->label('CTA button URL (optional)'),
        ];
    }

    private static function statsRepeater(string $l): Repeater
    {
        return Repeater::make("$l.stats")
            ->label('Statistics')
            ->schema([
                TextInput::make('value')->label('Value')->required()->helperText('e.g. "12+" or "Thousands"'),
                TextInput::make('label')->label('Label')->required()->helperText('e.g. "Years teaching experience"'),
            ])
            ->defaultItems(0)
            ->reorderable()
            ->addActionLabel('Add statistic');
    }

    private static function certificatesFields(string $l): array
    {
        return [
            TextInput::make("$l.certs_heading")
                ->label('Section heading'),
            Repeater::make("$l.certificates")
                ->label('Certificates')
                ->schema([
                    FileUpload::make('image')
                        ->label('Certificate image')
                        ->image()
                        ->disk('public')
                        ->directory('about/certificates')
                        ->saveUploadedFileUsing(MediaLibraryUploads::callback())
                        ->nullable()
                        ->helperText('Optional — a placeholder is shown if left empty.'),
                    TextInput::make('title')
                        ->label('Title')
                        ->required(),
                    TextInput::make('subtitle')
                        ->label('Subtitle')
                        ->nullable(),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->nullable(),
                    TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->nullable()
                        ->helperText('Lower numbers show first. Leave blank to keep the order added.'),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Add certificate'),
        ];
    }

    private static function galleryFields(string $l): array
    {
        return [
            TextInput::make("$l.gallery_heading")
                ->label('Section heading')
                ->helperText('Only shown once at least one image is added below.'),
            Repeater::make("$l.gallery")
                ->label('Gallery images')
                ->schema([
                    FileUpload::make('image')
                        ->label('Image')
                        ->image()
                        ->disk('public')
                        ->directory('about/gallery')
                        ->saveUploadedFileUsing(MediaLibraryUploads::callback())
                        ->required(),
                    TextInput::make('alt')
                        ->label('Alt text')
                        ->required()
                        ->helperText('Describes the image for accessibility and SEO.'),
                    TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->nullable()
                        ->helperText('Lower numbers show first. Leave blank to keep the order added.'),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Add image'),
        ];
    }

    private static function timelineFields(string $l): array
    {
        return [
            TextInput::make("$l.timeline_heading")
                ->label('Section heading'),
            Repeater::make("$l.timeline")
                ->label('Timeline items')
                ->schema([
                    TextInput::make('year')
                        ->label('Year')
                        ->required(),
                    TextInput::make('title')
                        ->label('Title')
                        ->required(),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->nullable(),
                    TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->nullable()
                        ->helperText('Lower numbers show first. Leave blank to keep the order added.'),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Add timeline item'),
        ];
    }

    private static function ctaFields(string $l): array
    {
        return [
            TextInput::make("$l.cta_title")
                ->label('Title (optional)'),
            Textarea::make("$l.cta_description")
                ->label('Description (optional)')
                ->rows(2),
            TextInput::make("$l.cta_button_text")
                ->label('Button text'),
            TextInput::make("$l.cta_button_url")
                ->label('Button URL'),
            MediaPickerInput::make("$l.cta_bg_image")
                ->label('Background image (optional)')
                ->onlyImages()
                ->uploadDirectory('about/cta')
                ->nullable()
                ->helperText('Leave empty to keep the default dark gradient background.'),
        ];
    }

    private static function seoFields(string $l): array
    {
        return [
            TextInput::make("$l.seo_title")
                ->label('Meta title'),
            Textarea::make("$l.seo_description")
                ->label('Meta description')
                ->rows(3),
            MediaPickerInput::make("$l.seo_og_image")
                ->label('Open Graph image')
                ->onlyImages()
                ->uploadDirectory('about/seo')
                ->nullable()
                ->helperText('Shown as the preview image when this page is shared on social media.'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['en', 'tr'] as $locale) {
            foreach (array_merge(self::TEXT_KEYS, self::FILE_KEYS) as $key) {
                SiteSetting::set("about.$locale.$key", self::normalizeUpload($state[$locale][$key] ?? null), 'about');
            }

            foreach (['certificates', 'gallery'] as $key) {
                $items = array_map(
                    fn ($item) => [...$item, 'image' => self::normalizeUpload($item['image'] ?? null)],
                    array_values($state[$locale][$key] ?? [])
                );
                SiteSetting::set("about.$locale.$key", json_encode($items, JSON_UNESCAPED_UNICODE), 'about');
            }

            foreach (['stats', 'timeline'] as $key) {
                SiteSetting::set(
                    "about.$locale.$key",
                    json_encode(array_values($state[$locale][$key] ?? []), JSON_UNESCAPED_UNICODE),
                    'about'
                );
            }
        }

        Notification::make()
            ->success()
            ->title('About page settings saved')
            ->send();
    }

    // FileUpload گاهی مقدار را به‌صورت آرایه برمی‌گرداند —
    // اولین مسیر را برمی‌داریم تا در ستون متنی ذخیره‌شدنی باشد
    private static function normalizeUpload($value)
    {
        if (is_array($value)) {
            return array_values(array_filter($value))[0] ?? null;
        }

        return $value;
    }
}
