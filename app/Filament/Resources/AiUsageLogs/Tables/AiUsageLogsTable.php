<?php

namespace App\Filament\Resources\AiUsageLogs\Tables;

use App\Models\AiUsageLog;
use App\Services\AiAssistant\ProviderManager;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AiUsageLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('provider_slug')
                    ->label('Provider')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('model')
                    ->label('Model')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('action_key')
                    ->label('Action')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('prompt_tokens')
                    ->label('Prompt tokens')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('completion_tokens')
                    ->label('Completion tokens')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('total_tokens')
                    ->label('Total tokens')
                    ->default('—'),

                TextColumn::make('estimated_cost_usd')
                    ->label('Est. cost')
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? '$'.number_format((float) $state, 6) : '—'),

                TextColumn::make('response_time_ms')
                    ->label('Response time')
                    ->formatStateUsing(fn (?int $state): string => $state !== null ? "{$state} ms" : '—'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'success' ? 'success' : 'danger'),

                TextColumn::make('user.name')
                    ->label('User')
                    ->default('—'),
            ])
            ->filters([
                SelectFilter::make('provider_slug')
                    ->label('Provider')
                    ->options(fn (): array => collect(ProviderManager::availableDrivers())->keys()->mapWithKeys(fn ($slug) => [$slug => ucfirst($slug)])->all()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['success' => 'Success', 'failed' => 'Failed']),

                SelectFilter::make('action_key')
                    ->label('Action')
                    ->options(fn (): array => AiUsageLog::whereNotNull('action_key')
                        ->distinct()->pluck('action_key', 'action_key')->all()),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name'),

                Filter::make('created_between')
                    ->schema([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                self::errorDetailAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::exportCsvBulkAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    // ============ متن کامل خطای پاک‌سازی‌شده — فقط برای ردیف‌های failed ============
    private static function errorDetailAction(): Action
    {
        return Action::make('errorDetail')
            ->label('Error')
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->visible(fn (AiUsageLog $record): bool => $record->status === 'failed' && filled($record->error_message))
            ->modalHeading(fn (AiUsageLog $record): string => 'Error — '.$record->created_at->format('Y-m-d H:i'))
            ->modalContent(fn (AiUsageLog $record) => view('filament.ai-studio.usage-log-error', ['log' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // ============ خروجی CSV از ردیف‌های انتخاب‌شده ============
    private static function exportCsvBulkAction(): BulkAction
    {
        return BulkAction::make('exportCsv')
            ->label('Export CSV')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (Collection $records) {
                $filename = 'ai-usage-logs-'.now()->format('Y-m-d-His').'.csv';

                return response()->streamDownload(function () use ($records): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Date', 'Provider', 'Model', 'Action', 'Prompt tokens', 'Completion tokens', 'Total tokens', 'Estimated cost (USD)', 'Response time (ms)', 'Status', 'User']);
                    foreach ($records as $r) {
                        fputcsv($out, [
                            optional($r->created_at)->format('Y-m-d H:i'),
                            $r->provider_slug,
                            $r->model,
                            $r->action_key,
                            $r->prompt_tokens,
                            $r->completion_tokens,
                            $r->total_tokens,
                            $r->estimated_cost_usd,
                            $r->response_time_ms,
                            $r->status,
                            $r->user->name ?? '',
                        ]);
                    }
                    fclose($out);
                }, $filename);
            })
            ->deselectRecordsAfterCompletion();
    }
}
