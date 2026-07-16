<?php

namespace App\Filament\Pages;

use App\Models\BrandMemorySection;
use App\Models\BrandMemoryValue;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentAssistantService;
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
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
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

    // بخشِ فعلاً انتخاب‌شده برای مشاهده‌ی تاریخچه — رندر پنل تاریخچه در بلید صرفاً یک شرط ساده‌ی
    // Livewire است، نه یک Filament Action تودرتو (اکشن‌های تعبیه‌شده داخل Schema::Section از طریق
    // callAction() قابل تست/فراخوانی مستقیم نیستند)
    public ?int $historySectionId = null;

    // نتیجه‌ی آخرین «Preview Prompt» — همان چیزی که واقعاً به ارائه‌دهنده‌ی هوش مصنوعی فرستاده
    // می‌شود، از App\Services\AiAssistant\ContentAssistantService::previewSystemPrompt() که خودِ
    // سازنده‌های خصوصی پرامپت را دوباره استفاده می‌کند (بدون هیچ منطق پرامپتی تکراری اینجا)
    public ?string $previewPromptResult = null;

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
                Html::make('<button type="button" wire:click="viewHistory('.$section->id.')" style="font-size:0.8rem;color:rgb(37 99 235);background:none;border:none;padding:0;cursor:pointer;text-decoration:underline;">View version history</button>'),
            ]);
    }

    public function viewHistory(int $sectionId): void
    {
        $this->historySectionId = $sectionId;
    }

    public function closeHistory(): void
    {
        $this->historySectionId = null;
    }

    public function closePreview(): void
    {
        $this->previewPromptResult = null;
    }

    public function getHistorySectionProperty(): ?BrandMemorySection
    {
        return $this->historySectionId ? BrandMemorySection::find($this->historySectionId) : null;
    }

    /**
     * نسخه‌های قبلیِ این بخش (همه‌ی زبان‌ها) — از همان مکانیزم spatie/laravel-activitylog که
     * BrandMemoryValue::getActivitylogOptions() فعال می‌کند، نه یک جدول نسخه‌ی جداگانه.
     */
    public function getHistoryActivitiesProperty()
    {
        if (! $this->historySectionId) {
            return collect();
        }

        $valueIds = BrandMemoryValue::where('brand_memory_section_id', $this->historySectionId)->pluck('id');

        return Activity::query()
            ->where('log_name', 'brand_memory_value')
            ->where('subject_type', (new BrandMemoryValue)->getMorphClass())
            ->whereIn('subject_id', $valueIds)
            ->with('subject')
            ->latest('id')
            ->limit(20)
            ->get();
    }

    /**
     * بازیابی یک نسخه‌ی قدیمی — یعنی content همان لحظه (attribute_changes.attributes.content) را
     * دوباره روی BrandMemoryValue می‌نویسد؛ چون از طریق مدل (نه query builder) انجام می‌شود، خودش
     * هم یک رویداد فعالیت تازه ثبت می‌کند — تاریخچه هرگز حذف/بازنویسی نمی‌شود، فقط اضافه می‌شود.
     */
    public function restoreVersion(int $activityId): void
    {
        $activity = Activity::query()->where('log_name', 'brand_memory_value')->findOrFail($activityId);
        $content = $activity->attribute_changes['attributes']['content'] ?? null;

        BrandMemoryValue::findOrFail($activity->subject_id)->update(['content' => $content]);

        Notification::make()->success()->title('Version restored')->send();

        $this->form->fill($this->loadState());
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
            Action::make('previewPrompt')
                ->label('Preview Prompt')
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->schema([
                    Select::make('field')
                        ->label('Field')
                        ->options(fn () => collect(ActionRegistry::all())->map(fn (array $d) => $d['label']))
                        ->native(false)
                        ->live()
                        ->required(),
                    Select::make('mode')
                        ->label('Mode')
                        ->options(fn (Get $get) => ActionRegistry::exists($get('field') ?? '')
                            ? array_combine(ActionRegistry::for($get('field'))['modes'], ActionRegistry::for($get('field'))['modes'])
                            : [])
                        ->native(false)
                        ->required()
                        ->visible(fn (Get $get) => filled($get('field'))),
                    Select::make('locale')
                        ->label('Content locale')
                        ->options(['en' => 'English', 'tr' => 'Turkish'])
                        ->native(false)
                        ->default('en')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->previewPromptResult = app(ContentAssistantService::class)
                        ->previewSystemPrompt($data['field'], $data['mode'], $data['locale']);
                }),

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
