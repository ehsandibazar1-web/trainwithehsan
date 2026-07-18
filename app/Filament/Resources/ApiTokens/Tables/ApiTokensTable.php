<?php

namespace App\Filament\Resources\ApiTokens\Tables;

use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApiTokensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('prefix')
                    ->label('Token starts with')
                    ->formatStateUsing(fn (string $state): string => $state.'…'),

                TextColumn::make('last_used_at')
                    ->label('Last used')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('Never used')
                    ->sortable(),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('Never')
                    ->color(fn (?string $state): ?string => $state && now()->greaterThan($state) ? 'danger' : null)
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Revoke')
                    ->modalHeading('Revoke this token?')
                    ->modalDescription('Anything still using it will immediately stop working.'),
            ])
            ->emptyStateHeading('No API tokens yet')
            ->emptyStateDescription('Create a token to let an AI (e.g. Claude) send articles straight into the Draft Queue through the secure import API.')
            ->defaultSort('created_at', 'desc');
    }
}
