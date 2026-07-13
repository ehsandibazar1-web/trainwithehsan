<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Activity Log';

    protected static ?string $title = 'Activity Log';

    protected string $view = 'filament.pages.activity-log-page';

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query())
            ->columns([
                TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System (auto-publish)'),

                TextColumn::make('subject.title')
                    ->label('Article')
                    ->default('— (deleted)')
                    ->limit(40),

                TextColumn::make('description')
                    ->label('Action')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        str_contains($state ?? '', 'published') => 'success',
                        str_contains($state ?? '', 'created') => 'info',
                        str_contains($state ?? '', 'deleted') => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
