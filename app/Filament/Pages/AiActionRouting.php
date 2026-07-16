<?php

namespace App\Filament\Pages;

use App\Models\AiActionOverride;
use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Services\AiAssistant\ActionRegistry;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * انتخاب ارائه‌دهنده‌ی پیش‌فرض/failover سراسری + override دانه‌ریز (per-field، نه per-category)
 * برای هر کلید App\Services\AiAssistant\ActionRegistry — دقیقاً طبق تصمیم تأییدشده‌ی کاربر: در UI
 * زیر سرتیترهای قابل‌جمع‌شدن (SEO/Content/Translation/Media/Links/Schema) گروه‌بندی می‌شوند، اما هر
 * فیلد override مستقل خودش را دارد و مقدار پیش‌فرض هر فیلد «Use Default Provider» است (یعنی هیچ
 * ردیفی در ai_action_overrides برایش نباشد). App\Services\AiAssistant\ProviderManager تنها
 * مصرف‌کننده‌ی این دو جدول (ai_provider_settings و ai_action_overrides) در زمان اجراست.
 */
class AiActionRouting extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'AI Routing';

    protected static ?string $title = 'AI Provider Routing';

    protected string $view = 'filament.pages.ai-action-routing';

    public ?array $data = [];

    // فیلدهای ActionRegistry فقط برای نمایش زیر این سرتیترها دسته‌بندی می‌شوند — خودِ override
    // همچنان per-field ذخیره می‌شود، این آرایه صرفاً چینش بصری صفحه است
    private const SECTIONS = [
        'SEO' => ['seo_title', 'meta_description', 'og_title', 'og_description', 'slug'],
        'Content' => ['excerpt', 'body', 'faq', 'outline', 'cta', 'tags', 'category', 'content_review_summary'],
        'Translation' => ['translate'],
        'Media' => ['alt_text', 'caption'],
        'Links' => ['internal_links', 'external_links'],
        'Schema' => ['schema'],
    ];

    public function mount(): void
    {
        $settings = AiProviderSetting::current();

        $state = [
            'default_provider_config_id' => $settings->default_provider_config_id,
            'failover_enabled' => $settings->failover_enabled,
            'fallback_provider_config_id' => $settings->fallback_provider_config_id,
        ];

        $overrides = AiActionOverride::all()->keyBy('action_key');

        foreach (ActionRegistry::all() as $key => $field) {
            $state['overrides'][$key]['provider'] = $overrides[$key]->ai_provider_config_id ?? null;
            $state['overrides'][$key]['model'] = $overrides[$key]->model ?? null;
        }

        $this->form->fill($state);
    }

    public function form(Schema $schema): Schema
    {
        $sections = [
            Section::make('Global defaults')
                ->description('Used whenever a field below is left on "Use Default Provider", and whenever nothing here is configured at all (falls back to the ANTHROPIC_API_KEY in .env).')
                ->schema([
                    self::providerSelect('default_provider_config_id', 'Default provider'),
                    Toggle::make('failover_enabled')
                        ->label('Enable failover')
                        ->helperText('If the chosen provider for a request fails, automatically retry with the fallback provider below.')
                        ->live(),
                    self::providerSelect('fallback_provider_config_id', 'Fallback provider')
                        ->visible(fn (Get $get): bool => (bool) $get('failover_enabled')),
                ])
                ->columns(2),
        ];

        foreach (self::SECTIONS as $label => $keys) {
            $sections[] = Section::make($label)
                ->schema(self::fieldsFor($keys))
                ->collapsible()
                ->collapsed();
        }

        return $schema->components($sections)->statePath('data');
    }

    /** @param  string[]  $keys */
    private static function fieldsFor(array $keys): array
    {
        $registry = ActionRegistry::all();
        $components = [];

        foreach ($keys as $key) {
            $field = $registry[$key] ?? null;

            if (! $field) {
                continue;
            }

            $components[] = Grid::make(2)->schema([
                self::providerSelect("overrides.$key.provider", $field['label'])
                    ->live(),
                TextInput::make("overrides.$key.model")
                    ->label('Model override (optional)')
                    ->helperText("Leave blank to use that provider's default model.")
                    ->visible(fn (Get $get) => filled($get("overrides.$key.provider"))),
            ]);
        }

        return $components;
    }

    private static function providerSelect(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn () => AiProviderConfig::where('is_enabled', true)->pluck('name', 'id'))
            ->native(false)
            ->placeholder('Use Default Provider')
            ->nullable();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        AiProviderSetting::current()->update([
            'default_provider_config_id' => $state['default_provider_config_id'] ?? null,
            'failover_enabled' => (bool) ($state['failover_enabled'] ?? false),
            'fallback_provider_config_id' => ($state['failover_enabled'] ?? false) ? ($state['fallback_provider_config_id'] ?? null) : null,
        ]);

        foreach ($state['overrides'] ?? [] as $actionKey => $override) {
            $providerId = $override['provider'] ?? null;

            if (! $providerId) {
                AiActionOverride::where('action_key', $actionKey)->delete();

                continue;
            }

            AiActionOverride::updateOrCreate(
                ['action_key' => $actionKey],
                ['ai_provider_config_id' => $providerId, 'model' => $override['model'] ?? null],
            );
        }

        Notification::make()
            ->success()
            ->title('AI routing settings saved')
            ->send();
    }
}
