<?php

namespace App\Filament\Resources\AiProfiles\Tables;

use App\Models\AiProfile;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('provider')
                    ->label('Provider')
                    ->badge(),

                TextColumn::make('defaults')
                    ->label('Defaults')
                    ->state(fn (AiProfile $record): string => implode(' · ', array_map(
                        fn ($k, $v) => "$k: $v",
                        array_keys($record->importDefaults()),
                        $record->importDefaults(),
                    )) ?: '—'),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No AI profiles yet')
            ->emptyStateDescription('A profile names the AI you use (Claude, ChatGPT, …) and fills in defaults like language or status when the pasted content leaves them out.')
            ->defaultSort('updated_at', 'desc');
    }
}
