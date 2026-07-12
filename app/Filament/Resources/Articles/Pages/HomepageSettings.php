<?php

namespace App\Filament\Pages;

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

class HomepageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Homepage Settings';

    protected static ?string $title = 'Homepage Settings';

    protected string $view = 'filament.pages.homepage-settings';

    public ?array $data = [];

    // کلیدهای متنی ساده — برای هر دو زبان یکسان تعریف می‌شوند
    private const TEXT_KEYS = [
        'hero1_title', 'hero1_sub',
        'hero2_title', 'hero2_sub',
        'hero3_title', 'hero3_sub',
        'video1_caption', 'video1_embed',
        'video2_caption', 'video2_embed',
        'video3_caption', 'video3_embed',
        'app_title', 'app_subtitle', 'app_text', 'app_button_label',
        'courses_title', 'courses_subtitle',
        'course1_label', 'course2_label', 'course3_label',
        'members_title', 'members_subtitle', 'members_button_label',
        'insta_url',
    ];

    // کلیدهای فایل (عکس/ویدیو) — مقدارشان مسیر فایل روی دیسک public است
    private const FILE_KEYS = [
        'video1_file', 'video2_file', 'video3_file',
        'app_image',
        'course1_image', 'course2_image', 'course3_image',
        'insta1_image', 'insta2_image',
    ];

    public function mount(): void
    {
        $state = [];

        foreach (['en', 'tr'] as $locale) {
            foreach (self::TEXT_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("home.$locale.$key");
            }
            foreach (self::FILE_KEYS as $key) {
                $state[$locale][$key] = SiteSetting::get("home.$locale.$key");
            }
            $state[$locale]['members'] = SiteSetting::getJson("home.$locale.members");
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('English — Hero Slider')
                    ->schema(self::heroFields('en')),
                Section::make('English — Video Row')
                    ->schema(self::videoFields('en'))
                    ->collapsed(),
                Section::make('English — App Section')
                    ->schema(self::appFields('en'))
                    ->collapsed(),
                Section::make('English — Courses Section')
                    ->schema(self::coursesFields('en'))
                    ->collapsed(),
                Section::make('English — Member Results')
                    ->schema(self::membersFields('en'))
                    ->collapsed(),
                Section::make('English — Instagram')
                    ->schema(self::instaFields('en'))
                    ->collapsed(),

                Section::make('Türkçe — Hero Slider')
                    ->schema(self::heroFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Video Row')
                    ->schema(self::videoFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — App Section')
                    ->schema(self::appFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Courses Section')
                    ->schema(self::coursesFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Member Results')
                    ->schema(self::membersFields('tr'))
                    ->collapsed(),
                Section::make('Türkçe — Instagram')
                    ->schema(self::instaFields('tr'))
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    private static function heroFields(string $l): array
    {
        return [
            TextInput::make("$l.hero1_title")->label('Slide 1 — Title'),
            Textarea::make("$l.hero1_sub")->label('Slide 1 — Subtitle')->rows(2),
            TextInput::make("$l.hero2_title")->label('Slide 2 — Title'),
            Textarea::make("$l.hero2_sub")->label('Slide 2 — Subtitle')->rows(2),
            TextInput::make("$l.hero3_title")->label('Slide 3 — Title'),
            Textarea::make("$l.hero3_sub")->label('Slide 3 — Subtitle')->rows(2),
        ];
    }

    private static function videoFields(string $l): array
    {
        $fields = [];
        foreach ([1, 2, 3] as $i) {
            $fields[] = TextInput::make("$l.video{$i}_caption")->label("Video $i — Caption");
            $fields[] = TextInput::make("$l.video{$i}_embed")
                ->label("Video $i — Embed URL (YouTube/Aparat)")
                ->helperText('Paste an embed link here, OR upload a file below — not both.');
            $fields[] = FileUpload::make("$l.video{$i}_file")
                ->label("Video $i — File (mp4)")
                ->disk('public')
                ->directory('homepage/videos')
                ->acceptedFileTypes(['video/mp4'])
                ->nullable();
        }
        return $fields;
    }

    private static function appFields(string $l): array
    {
        return [
            TextInput::make("$l.app_title")->label('Title'),
            TextInput::make("$l.app_subtitle")->label('Subtitle'),
            Textarea::make("$l.app_text")->label('Text')->rows(4),
            TextInput::make("$l.app_button_label")->label('Button label'),
            FileUpload::make("$l.app_image")
                ->label('Section image')
                ->image()
                ->disk('public')
                ->directory('homepage')
                ->nullable(),
        ];
    }

    private static function coursesFields(string $l): array
    {
        $fields = [
            TextInput::make("$l.courses_title")->label('Section title'),
            Textarea::make("$l.courses_subtitle")->label('Section subtitle')->rows(2),
        ];
        foreach ([1, 2, 3] as $i) {
            $fields[] = TextInput::make("$l.course{$i}_label")->label("Course $i — Label");
            $fields[] = FileUpload::make("$l.course{$i}_image")
                ->label("Course $i — Image")
                ->image()
                ->disk('public')
                ->directory('homepage/courses')
                ->nullable();
        }
        return $fields;
    }

    private static function membersFields(string $l): array
    {
        return [
            TextInput::make("$l.members_title")->label('Section title'),
            Textarea::make("$l.members_subtitle")->label('Section subtitle')->rows(2),
            TextInput::make("$l.members_button_label")->label('Button label'),
            Repeater::make("$l.members")
                ->label('Members')
                ->schema([
                    TextInput::make('name')->label('Name'),
                    FileUpload::make('photo')
                        ->label('Photo')
                        ->image()
                        ->disk('public')
                        ->directory('homepage/members')
                        ->nullable(),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add member'),
        ];
    }

    private static function instaFields(string $l): array
    {
        return [
            TextInput::make("$l.insta_url")->label('Instagram URL'),
            FileUpload::make("$l.insta1_image")
                ->label('Band 1 image')
                ->image()
                ->disk('public')
                ->directory('homepage')
                ->nullable(),
            FileUpload::make("$l.insta2_image")
                ->label('Band 2 image')
                ->image()
                ->disk('public')
                ->directory('homepage')
                ->nullable(),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['en', 'tr'] as $locale) {
            foreach (array_merge(self::TEXT_KEYS, self::FILE_KEYS) as $key) {
                SiteSetting::set(
                    "home.$locale.$key",
                    $state[$locale][$key] ?? null,
                    'homepage'
                );
            }

            SiteSetting::set(
                "home.$locale.members",
                json_encode($state[$locale]['members'] ?? [], JSON_UNESCAPED_UNICODE),
                'homepage'
            );
        }

        Notification::make()
            ->success()
            ->title('Homepage settings saved')
            ->send();
    }
}
