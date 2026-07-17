<?php

namespace App\Filament\Resources\AiProviderConfigs\Schemas;

use App\Models\AiProviderConfig;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiProviderConfigForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Connection')
                    ->schema([
                        TextInput::make('name')
                            ->label('Provider name')
                            ->required(),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Fixed identifier used internally to select the matching provider class — cannot be changed here.'),

                        // هرگز مقدار رمزگشایی‌شده را پر نمی‌کند — طبق «Never expose them in the
                        // UI after saving»؛ dehydrated(false) وقتی خالی رها شود یعنی مقدار قبلی
                        // در دیتابیس دست‌نخورده می‌ماند
                        TextInput::make('api_key')
                            ->label('API Key')
                            ->password()
                            ->revealable()
                            ->afterStateHydrated(fn ($component) => $component->state(null))
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->placeholder(fn ($record) => $record?->api_key ? '••••••••••••••••  (leave blank to keep the current key)' : 'Not set yet')
                            ->helperText('Stored encrypted. Leave blank to keep the existing key unchanged.'),

                        TextInput::make('base_url')
                            ->label('Base URL (optional)')
                            ->url()
                            ->nullable()
                            ->helperText("Only needed for a custom/self-hosted endpoint — leave blank to use this provider's default API URL."),

                        TextInput::make('default_model')
                            ->label('Default model')
                            ->nullable()
                            ->helperText('The model ID sent in API requests, e.g. claude-sonnet-4-5, gpt-5, gemini-2.5-pro. See "Known models" below.'),

                        TextInput::make('max_tokens')
                            ->label('Max tokens')
                            ->numeric()
                            ->nullable(),

                        TextInput::make('temperature')
                            ->label('Temperature')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(2)
                            ->nullable(),

                        TextInput::make('timeout_seconds')
                            ->label('Timeout (seconds)')
                            ->numeric()
                            ->nullable(),

                        Toggle::make('is_enabled')
                            ->label('Enabled')
                            ->helperText('Must be enabled AND have an API key set before it can be chosen as the default or a per-field provider.'),

                        // فقط OpenAI/Gemini واقعاً یک API عمومیِ embedding دارند (نگاه کنید به
                        // AiProviderConfig::EMBEDDING_CAPABLE_SLUGS و CLAUDE.md، بخش RAG) — روی
                        // بقیه‌ی ارائه‌دهنده‌ها این فیلد اصلاً نمایش داده نمی‌شود تا ادمین گمان
                        // نکند اینجا هم قابل‌تنظیم است
                        TextInput::make('embedding_model')
                            ->label('Embedding model')
                            ->nullable()
                            ->visible(fn (?AiProviderConfig $record): bool => in_array($record?->slug, AiProviderConfig::EMBEDDING_CAPABLE_SLUGS, true))
                            ->helperText('The embedding model ID, e.g. "text-embedding-3-small" (OpenAI) or "text-embedding-004" (Gemini). Required before this provider can be picked in AI Routing → Embeddings.'),

                        // فقط OpenAI/Gemini واقعاً یک API عمومیِ تولید تصویر دارند (نگاه کنید به
                        // AiProviderConfig::IMAGE_GENERATION_CAPABLE_SLUGS) — همان الگوی embedding_model بالا
                        TextInput::make('image_model')
                            ->label('Image model')
                            ->nullable()
                            ->visible(fn (?AiProviderConfig $record): bool => in_array($record?->slug, AiProviderConfig::IMAGE_GENERATION_CAPABLE_SLUGS, true))
                            ->helperText('The image-generation model ID, e.g. "gpt-image-1" or "dall-e-3" (OpenAI), "imagen-3.0-generate-002" (Gemini). Required before this provider can be picked in AI Routing → Image Generation.'),
                    ])
                    ->columns(2),

                Section::make('Known models')
                    ->description('An admin-maintained catalog of model IDs for this provider — used to fill "Default model" and per-field overrides, and to estimate cost in Usage Logs when pricing is set.')
                    ->schema([
                        Repeater::make('models')
                            ->relationship()
                            ->label('Models')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Label')
                                    ->required()
                                    ->helperText('e.g. "Claude Sonnet 4.5"'),
                                TextInput::make('model')
                                    ->label('Model ID')
                                    ->required()
                                    ->helperText('e.g. "claude-sonnet-4-5"'),
                                TextInput::make('input_price_per_million')
                                    ->label('Input $ / 1M tokens')
                                    ->numeric()
                                    ->nullable(),
                                TextInput::make('output_price_per_million')
                                    ->label('Output $ / 1M tokens')
                                    ->numeric()
                                    ->nullable(),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add model')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }
}
