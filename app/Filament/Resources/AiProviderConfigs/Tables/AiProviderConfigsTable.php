<?php

namespace App\Filament\Resources\AiProviderConfigs\Tables;

use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Services\AiAssistant\ProviderManager;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProviderConfigsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Provider')
                    ->searchable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_enabled')
                    ->label('Enabled')
                    ->boolean(),

                TextColumn::make('default_flag')
                    ->label('Default')
                    ->state(fn (AiProviderConfig $record): ?string => AiProviderSetting::current()->default_provider_config_id === $record->id ? 'Default' : null)
                    ->badge()
                    ->color('success')
                    ->placeholder('—'),

                TextColumn::make('last_test_status')
                    ->label('Last test')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'success' => 'Connected',
                        'failed' => 'Failed',
                        default => 'Never tested',
                    }),

                TextColumn::make('last_test_latency_ms')
                    ->label('Latency')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} ms" : '—'),

                TextColumn::make('last_tested_at')
                    ->label('Last tested')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('testConnection')
                    ->label('Test Connection')
                    ->icon(Heroicon::OutlinedBolt)
                    ->color('gray')
                    ->action(function (AiProviderConfig $record): void {
                        $result = app(ProviderManager::class)->testConnection($record);

                        if ($result['status'] === 'success') {
                            Notification::make()
                                ->success()
                                ->title('Connected')
                                ->body("Latency: {$result['latency_ms']}ms · Model: {$result['model']}")
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Connection failed')
                                ->body($result['error'] ?? 'Unknown error.')
                                ->send();
                        }
                    }),

                Action::make('setDefault')
                    ->label('Set as Default')
                    ->icon(Heroicon::OutlinedStar)
                    ->color('gray')
                    ->visible(fn (AiProviderConfig $record): bool => AiProviderSetting::current()->default_provider_config_id !== $record->id)
                    ->action(function (AiProviderConfig $record): void {
                        AiProviderSetting::current()->update(['default_provider_config_id' => $record->id]);

                        Notification::make()
                            ->success()
                            ->title("{$record->name} is now the default provider")
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->emptyStateHeading('No AI providers')
            ->defaultSort('name');
    }
}
