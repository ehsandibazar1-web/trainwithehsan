<?php

namespace App\Filament\Pages;

use App\Models\BrandMemorySection;
use App\Models\BrandMemoryValue;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * حافظه‌ی برند — دانش دائمی برند که هر سه سازنده‌ی system prompt در ContentAssistantService
 * به‌صورت خودکار می‌خوانند (نگاه کنید به App\Services\BrandMemory\BrandMemoryService و
 * CLAUDE.md). این صفحه فقط مدیریت بخش‌ها/مقادیر است؛ منطق ترکیب پرامپت اینجا تکرار نمی‌شود.
 */
class BrandMemory extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Brand Memory';

    protected static ?string $title = 'Brand Memory';

    protected string $view = 'filament.pages.brand-memory';

    // زبان‌های مقدارِ حافظه‌ی برند — جدا از زبان‌های واقعی سایت (en/tr در Article/Page)؛ fa اینجا
    // فقط برای محتوای مرجع/دانش برند مجاز است، نه یک لوکیل واقعی صفحات عمومی (نگاه کنید به CLAUDE.md)
    public const LOCALES = ['en' => 'English', 'tr' => 'Turkish', 'fa' => 'Persian'];

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->loadState());
    }

    private function loadState(): array
    {
        $sections = BrandMemorySection::query()->with('values')->orderBy('sort_order')->get();

        $state = ['enabled' => [], 'values' => []];

        foreach ($sections as $section) {
            $state['enabled'][$section->id] = $section->is_enabled;

            foreach (array_keys(self::LOCALES) as $locale) {
                $state['values'][$section->id][$locale] = $section->valueFor($locale)?->content;
            }
        }

        return $state;
    }

    public function form(Schema $schema): Schema
    {
        $groups = BrandMemorySection::query()->orderBy('sort_order')->get()->groupBy('group');

        $components = $groups->map(fn ($items, $group) => Section::make($group)
            ->collapsible()
            ->schema($items->map(fn (BrandMemorySection $section) => self::sectionSchema($section))->all())
        )->values()->all();

        return $schema->components($components)->statePath('data');
    }

    private static function sectionSchema(BrandMemorySection $section): Section
    {
        $tabs = collect(self::LOCALES)->map(fn (string $label, string $locale) => Tab::make($label)->schema([
            Textarea::make("values.{$section->id}.{$locale}")
                ->label(false)
                ->rows(3)
                ->placeholder("Write \"{$section->label}\" in {$label}..."),
        ]))->values()->all();

        return Section::make($section->label)
            ->description($section->description)
            ->collapsible()
            ->collapsed()
            ->schema([
                Toggle::make("enabled.{$section->id}")
                    ->label('Included in AI prompts')
                    ->inline(false),
                Tabs::make("tabs_{$section->id}")->tabs($tabs),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state['enabled'] ?? [] as $sectionId => $enabled) {
            BrandMemorySection::whereKey($sectionId)->update(['is_enabled' => (bool) $enabled]);
        }

        foreach ($state['values'] ?? [] as $sectionId => $locales) {
            foreach ($locales as $locale => $content) {
                $existing = BrandMemoryValue::query()
                    ->where('brand_memory_section_id', $sectionId)
                    ->where('locale', $locale)
                    ->first();

                if (blank($content) && ! $existing) {
                    continue;
                }

                BrandMemoryValue::updateOrCreate(
                    ['brand_memory_section_id' => $sectionId, 'locale' => $locale],
                    ['content' => filled($content) ? $content : null],
                );
            }
        }

        Notification::make()->success()->title('Brand Memory saved')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addSection')
                ->label('Add Custom Section')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('label')->label('Section Name')->required(),
                    TextInput::make('group')
                        ->label('Group')
                        ->required()
                        ->datalist(fn () => BrandMemorySection::query()->distinct()->orderBy('group')->pluck('group')->all()),
                    Textarea::make('description')->label('Description (optional)')->rows(2),
                ])
                ->action(function (array $data): void {
                    BrandMemorySection::create([
                        'key' => Str::slug($data['label'], '_').'_'.Str::lower(Str::random(4)),
                        'label' => $data['label'],
                        'group' => $data['group'],
                        'description' => $data['description'] ?? null,
                        'is_enabled' => true,
                        'is_system' => false,
                        'sort_order' => (BrandMemorySection::max('sort_order') ?? 0) + 1,
                    ]);

                    Notification::make()->success()->title('Section added')->send();

                    $this->redirect(static::getUrl());
                }),

            Action::make('deleteSection')
                ->label('Delete Custom Section')
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->visible(fn (): bool => BrandMemorySection::where('is_system', false)->exists())
                ->requiresConfirmation()
                ->modalDescription('This permanently deletes the section and its values in every language. This cannot be undone.')
                ->schema([
                    Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => BrandMemorySection::where('is_system', false)->orderBy('label')->pluck('label', 'id'))
                        ->required(),
                ])
                ->action(function (array $data): void {
                    BrandMemorySection::where('id', $data['section_id'])->where('is_system', false)->delete();

                    Notification::make()->success()->title('Section deleted')->send();

                    $this->redirect(static::getUrl());
                }),
        ];
    }
}
