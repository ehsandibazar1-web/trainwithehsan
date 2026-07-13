<?php

namespace App\Filament\Resources\Articles\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(45),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),

                TextColumn::make('category')
                    ->label('Category')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'scheduled' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('views')
                    ->label('Views')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->label('Language')
                    ->options([
                        'en' => 'English',
                        'tr' => 'Türkçe',
                    ]),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::scheduleBulkAction(),
                    self::cancelScheduleBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ============ زمان‌بندی گروهی ============
    private static function scheduleBulkAction(): BulkAction
    {
        return BulkAction::make('bulkSchedule')
            ->label('Schedule selected')
            ->icon('heroicon-o-calendar-days')
            ->schema([
                Select::make('pattern')
                    ->label('Pattern')
                    ->options([
                        'daily' => 'One article every day',
                        'every_n_days' => 'One article every N days',
                        'weekly' => 'One article every week',
                        'specific_days' => 'Specific weekdays only',
                    ])
                    ->default('daily')
                    ->live()
                    ->required(),

                TextInput::make('interval_days')
                    ->label('Every how many days?')
                    ->numeric()
                    ->minValue(1)
                    ->default(2)
                    ->visible(fn (Get $get) => $get('pattern') === 'every_n_days')
                    ->required(fn (Get $get) => $get('pattern') === 'every_n_days'),

                CheckboxList::make('weekdays')
                    ->label('Which days?')
                    ->options([
                        0 => 'Sunday',
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                    ])
                    ->columns(4)
                    ->visible(fn (Get $get) => $get('pattern') === 'specific_days')
                    ->required(fn (Get $get) => $get('pattern') === 'specific_days'),

                DatePicker::make('start_date')
                    ->label('Start date')
                    ->default(now()->addDay()->toDateString())
                    ->minDate(now()->toDateString())
                    ->required(),

                TimePicker::make('time')
                    ->label('Publish time')
                    ->seconds(false)
                    ->default('09:00')
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $dates = self::buildScheduleDates(
                    count: $records->count(),
                    pattern: $data['pattern'],
                    intervalDays: (int) ($data['interval_days'] ?? 1),
                    weekdays: $data['weekdays'] ?? [],
                    startDate: $data['start_date'],
                    time: $data['time'],
                );

                $records->values()->each(function ($article, $i) use ($dates) {
                    $article->update([
                        'status' => 'scheduled',
                        'published_at' => $dates[$i],
                    ]);
                });

                Notification::make()
                    ->success()
                    ->title('Schedule applied to ' . $records->count() . ' article(s)')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    // ============ لغو زمان‌بندی گروهی ============
    private static function cancelScheduleBulkAction(): BulkAction
    {
        return BulkAction::make('cancelSchedule')
            ->label('Cancel schedule (→ Draft)')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $count = 0;
                foreach ($records as $article) {
                    if ($article->status === 'scheduled') {
                        $article->update(['status' => 'draft']);
                        $count++;
                    }
                }

                Notification::make()
                    ->success()
                    ->title($count . ' article(s) moved back to Draft')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    // ============ محاسبه‌ی تاریخ‌های انتشار بر اساس الگو ============
    private static function buildScheduleDates(
        int $count,
        string $pattern,
        int $intervalDays,
        array $weekdays,
        string $startDate,
        string $time,
    ): array {
        $dates = [];
        $cursor = Carbon::parse($startDate);

        if ($pattern === 'specific_days') {
            $weekdays = array_map('intval', $weekdays);
            while (count($dates) < $count) {
                if (in_array((int) $cursor->dayOfWeek, $weekdays, true)) {
                    $dates[] = self::combine($cursor, $time);
                }
                $cursor = $cursor->copy()->addDay();
            }

            return $dates;
        }

        $step = match ($pattern) {
            'weekly' => 7,
            'every_n_days' => max(1, $intervalDays),
            default => 1, // daily
        };

        for ($i = 0; $i < $count; $i++) {
            $dates[] = self::combine($cursor->copy()->addDays($i * $step), $time);
        }

        return $dates;
    }

    private static function combine(Carbon $date, string $time): Carbon
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $date->copy()->setTime((int) $hour, (int) $minute);
    }
}
