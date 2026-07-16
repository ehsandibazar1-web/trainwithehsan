<?php

namespace App\Filament\Resources\AiPrompts\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiPromptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(40)
                    ->default('—'),

                TextColumn::make('prompt')
                    ->label('Prompt')
                    ->limit(60)
                    ->copyable()
                    ->copyMessage('Prompt copied — paste it into your AI chat')
                    ->tooltip('Click to copy the full prompt'),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No prompts yet')
            ->emptyStateDescription('Save the instructions you give the AI here so you can copy them with one click next time.')
            ->defaultSort('updated_at', 'desc');
    }
}
