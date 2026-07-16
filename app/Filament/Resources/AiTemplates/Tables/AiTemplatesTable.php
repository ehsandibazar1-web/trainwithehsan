<?php

namespace App\Filament\Resources\AiTemplates\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiTemplatesTable
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
                    ->limit(50)
                    ->default('—'),

                TextColumn::make('format')
                    ->label('Format')
                    ->badge(),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No templates yet')
            ->emptyStateDescription('Save a reusable JSON or Markdown skeleton here, then load it with one click on the AI Import page.')
            ->defaultSort('updated_at', 'desc');
    }
}
