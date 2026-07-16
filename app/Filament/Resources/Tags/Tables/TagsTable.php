<?php

namespace App\Filament\Resources\Tags\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TagsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->default('#9ca3af'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('articles_count')
                    ->label('Articles')
                    ->counts('articles'),

                TextColumn::make('pages_count')
                    ->label('Pages')
                    ->counts('pages'),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No tags yet')
            ->emptyStateDescription('Tags organize content for the Content Planner\'s filters — create one here or inline while editing an Article/Page.')
            ->defaultSort('name');
    }
}
