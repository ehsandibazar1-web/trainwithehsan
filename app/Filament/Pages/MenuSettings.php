<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class MenuSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Menu Settings';

    protected static ?string $title = 'Menu Settings';

    protected string $view = 'filament.pages.menu-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'en' => ['items' => SiteSetting::getJson('menu.en.items')],
            'tr' => ['items' => SiteSetting::getJson('menu.tr.items')],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('English menu')
                    ->description('Header navigation — desktop and mobile. Leave empty to use the built-in default menu.')
                    ->schema([self::menuRepeater('en')]),

                Section::make('Türkçe menü')
                    ->description('Üst menü — masaüstü ve mobil. Boş bırakılırsa varsayılan menü kullanılır.')
                    ->schema([self::menuRepeater('tr')])
                    ->collapsed(),
            ])
            ->statePath('data');
    }

    private static function menuRepeater(string $l): Repeater
    {
        return Repeater::make("$l.items")
            ->label('Menu items')
            ->schema([
                TextInput::make('label')
                    ->label('Label')
                    ->required(),
                TextInput::make('url')
                    ->label('URL')
                    ->required()
                    ->helperText('Relative like /about or /tr/blog — or a full https:// link.'),
                Toggle::make('highlight')
                    ->label('Highlight (gold button)')
                    ->helperText('Shown as the gold call-to-action button at the end of the menu.'),
            ])
            ->defaultItems(0)
            ->reorderable()
            ->addActionLabel('Add menu item');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (['en', 'tr'] as $locale) {
            $items = array_values($state[$locale]['items'] ?? []);

            SiteSetting::set(
                "menu.$locale.items",
                json_encode($items, JSON_UNESCAPED_UNICODE),
                'menu'
            );
        }

        Notification::make()
            ->success()
            ->title('Menu settings saved')
            ->send();
    }
}
