<?php

namespace App\Filament\Pages;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Models\SiteSetting;
use BackedEnum;
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

class FooterSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Footer Settings';

    protected static ?string $title = 'Footer Settings';

    protected string $view = 'filament.pages.footer-settings';

    public ?array $data = [];

    // کلیدهای متنی ساده — برای هر دو زبان یکسان تعریف می‌شوند
    private const TEXT_KEYS = [
        'newsletter_title', 'newsletter_description', 'newsletter_placeholder', 'newsletter_button',
        'description', 'copyright',
        'contact_email', 'contact_phone', 'contact_address',
    ];

    // کلیدهای فایل (عکس) — مقدارشان مسیر فایل روی دیسک public است
    private const FILE_KEYS = [
        'bg_image', 'logo',
    ];

    // کلیدهای ریپیتر — به‌صورت JSON ذخیره می‌شوند
    private const REPEATER_KEYS = [
        'columns', 'socials',
    ];

    public function mount(): void
    {
        $state = [];

        foreach (['en', 'tr'] as $locale) {
            foreach (self::TEXT_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("footer.$locale.$key");
            }
            foreach (self::FILE_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("footer.$locale.$key");
            }
            foreach (self::REPEATER_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::getJson("footer.$locale.$key");
            }
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('English — Newsletter bar')
                    ->description('The gold bar above the footer. Leave a field empty to keep the current default text.')
                    ->schema(self::newsletterFields('en')),
                Section::make('English — Footer look')
                    ->schema(self::lookFields('en'))
                    ->collapsed(),
                Section::make('English — Link columns')
                    ->description('The link columns shown in the footer. Leave empty to keep the current default columns.')
                    ->schema([self::columnsRepeater('en')])
                    ->collapsed(),
                Section::make('English — Social & contact')
                    ->description('Optional — only shown in the footer once you fill them in.')
                    ->schema(self::socialContactFields('en'))
                    ->collapsed(),

                Section::make('Türkçe — Bülten çubuğu')
                    ->schema(self::newsletterFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Footer görünümü')
                    ->schema(self::lookFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Bağlantı sütunları')
                    ->schema([self::columnsRepeater('tr')])
                    ->collapsed(),
                Section::make('Türkçe — Sosyal medya ve iletişim')
                    ->schema(self::socialContactFields('tr'))
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    private static function newsletterFields(string $l): array
    {
        return [
            TextInput::make("$l.newsletter_title")->label('Title'),
            TextInput::make("$l.newsletter_description")->label('Description'),
            TextInput::make("$l.newsletter_placeholder")->label('Email box placeholder'),
            TextInput::make("$l.newsletter_button")->label('Button text'),
        ];
    }

    private static function lookFields(string $l): array
    {
        return [
            MediaPickerInput::make("$l.bg_image")
                ->label('Footer background image')
                ->onlyImages()
                ->uploadDirectory('footer')
                ->nullable()
                ->helperText('Leave empty to keep the current background.'),
            MediaPickerInput::make("$l.logo")
                ->label('Footer logo')
                ->onlyImages()
                ->uploadDirectory('footer')
                ->nullable()
                ->helperText('Leave empty to keep the current logo.'),
            Textarea::make("$l.description")
                ->label('Footer description (optional)')
                ->rows(2)
                ->helperText('Short text shown under the footer logo. Hidden while empty.'),
            TextInput::make("$l.copyright")
                ->label('Copyright text')
                ->helperText('Shown after the © and the current year — e.g. "Ehsan Dibazar. All rights reserved." Leave empty to keep the default.'),
        ];
    }

    private static function columnsRepeater(string $l): Repeater
    {
        return Repeater::make("$l.columns")
            ->label('Columns')
            ->schema([
                TextInput::make('title')
                    ->label('Column title')
                    ->required(),
                Repeater::make('links')
                    ->label('Links')
                    ->schema([
                        TextInput::make('label')->label('Label')->required(),
                        TextInput::make('url')->label('URL')->required()
                            ->helperText('Relative like /about or /tr/blog — or a full https:// link.'),
                    ])
                    ->defaultItems(0)
                    ->reorderable()
                    ->addActionLabel('Add link'),
            ])
            ->defaultItems(0)
            ->reorderable()
            ->addActionLabel('Add column');
    }

    private static function socialContactFields(string $l): array
    {
        return [
            Repeater::make("$l.socials")
                ->label('Social media links')
                ->schema([
                    TextInput::make('label')->label('Name')->required()->helperText('e.g. Instagram, YouTube, Telegram'),
                    TextInput::make('url')->label('URL')->required(),
                ])
                ->defaultItems(0)
                ->reorderable()
                ->addActionLabel('Add social link'),
            TextInput::make("$l.contact_email")->label('Contact email (optional)'),
            TextInput::make("$l.contact_phone")->label('Contact phone (optional)'),
            TextInput::make("$l.contact_address")->label('Address (optional)'),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['en', 'tr'] as $locale) {
            foreach (array_merge(self::TEXT_KEYS, self::FILE_KEYS) as $key) {
                SiteSetting::set("footer.$locale.$key", self::normalizeUpload($state[$locale][$key] ?? null), 'footer');
            }

            $columns = array_map(
                fn ($col) => [...$col, 'links' => array_values($col['links'] ?? [])],
                array_values($state[$locale]['columns'] ?? [])
            );
            SiteSetting::set("footer.$locale.columns", json_encode($columns, JSON_UNESCAPED_UNICODE), 'footer');

            SiteSetting::set(
                "footer.$locale.socials",
                json_encode(array_values($state[$locale]['socials'] ?? []), JSON_UNESCAPED_UNICODE),
                'footer'
            );
        }

        Notification::make()
            ->success()
            ->title('Footer settings saved')
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
