<?php

namespace App\Filament\Resources\ImportLogs\Tables;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\ImportLog;
use App\Services\ArticleImport\ArticleImportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ImportLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('by')
                    ->label('By')
                    ->state(fn (ImportLog $record): string => $record->user->name
                        ?? ($record->apiToken ? 'API: '.$record->apiToken->name : '—')),

                TextColumn::make('ai_provider')
                    ->label('AI Provider')
                    ->badge()
                    ->color('gray')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('format')
                    ->label('Format')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (ImportLog $record): string => $record->isRolledBack()
                        ? 'Rolled back'
                        : ucfirst($record->status))
                    ->color(fn (ImportLog $record): string => match (true) {
                        $record->isRolledBack() => 'warning',
                        $record->status === 'imported' => 'success',
                        $record->status === 'previewed' => 'info',
                        default => 'danger',
                    }),

                TextColumn::make('article_title')
                    ->label('Article')
                    ->limit(40)
                    ->default('—')
                    ->searchable()
                    ->url(fn (ImportLog $record): ?string => $record->article_id
                        ? ArticleResource::getUrl('edit', ['record' => $record->article_id])
                        : null),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge()
                    ->default('—'),

                TextColumn::make('faq_count')
                    ->label('FAQs')
                    ->toggleable(),

                TextColumn::make('image_count')
                    ->label('Images')
                    ->toggleable(),

                TextColumn::make('rolled_back_at')
                    ->label('Rolled back at')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Result')
                    ->options([
                        'imported' => 'Imported',
                        'failed' => 'Failed',
                        'previewed' => 'Previewed',
                    ]),

                SelectFilter::make('ai_provider')
                    ->label('AI Provider')
                    ->options(fn (): array => ImportLog::whereNotNull('ai_provider')
                        ->distinct()->pluck('ai_provider', 'ai_provider')->all()),

                SelectFilter::make('locale')
                    ->label('Language')
                    ->options(['en' => 'English', 'tr' => 'Türkçe']),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->relationship('user', 'name'),

                TernaryFilter::make('rolled_back')
                    ->label('Rolled back')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('rolled_back_at'),
                        false: fn ($query) => $query->whereNull('rolled_back_at'),
                    ),

                Filter::make('created_between')
                    ->schema([
                        DatePicker::make('from')->label('Imported from'),
                        DatePicker::make('until')->label('Imported until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                self::validationReportAction(),
                self::rollbackAction(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    // ============ گزارش اعتبارسنجی — خطاها/هشدارهای ذخیره‌شده‌ی همان اجرا ============
    private static function validationReportAction(): Action
    {
        return Action::make('validationReport')
            ->label('Report')
            ->icon('heroicon-o-document-magnifying-glass')
            ->color('gray')
            ->modalHeading(fn (ImportLog $record): string => 'Validation report — '.$record->created_at->format('Y-m-d H:i'))
            ->modalContent(fn (ImportLog $record) => view('filament.ai-studio.validation-report', ['log' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // ============ بازگردانی — حذف مقاله‌ی ایمپورت‌شده از طریق سرویس (نه مستقیم) ============
    private static function rollbackAction(): Action
    {
        return Action::make('rollback')
            ->label('Roll back')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->visible(fn (ImportLog $record): bool => $record->canRollBack())
            ->requiresConfirmation()
            ->modalHeading('Roll back this import?')
            ->modalDescription(fn (ImportLog $record): string => 'The article "'.$record->article_title.'" will be deleted from the site. Any downloaded image stays in the media library.')
            ->action(function (ImportLog $record): void {
                $result = app(ArticleImportService::class)->rollback($record, ['user_id' => auth()->id()]);

                Notification::make()
                    ->{$result['ok'] ? 'success' : 'danger'}()
                    ->title($result['message'])
                    ->send();
            });
    }
}
