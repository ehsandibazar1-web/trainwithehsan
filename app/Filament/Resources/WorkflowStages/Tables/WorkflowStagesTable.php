<?php

namespace App\Filament\Resources\WorkflowStages\Tables;

use App\Models\WorkflowStage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WorkflowStagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color')
                    ->label('')
                    ->default('#9ca3af'),

                TextColumn::make('label')
                    ->label('Stage')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_terminal')
                    ->label('Terminal')
                    ->boolean(),

                TextColumn::make('content_plans_count')
                    ->label('Cards')
                    ->counts('contentPlans'),

                TextColumn::make('checklist_items')
                    ->label('Checklist')
                    ->formatStateUsing(fn (?array $state): string => $state ? count($state).' item(s)' : '—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (WorkflowStage $record): bool => ! $record->contentPlans()->exists())
                    ->requiresConfirmation()
                    ->modalDescription('This stage has no cards in it, so it can be safely deleted.'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('No workflow stages')
            ->emptyStateDescription('The eight default stages (Idea through Archived) are seeded automatically — add a custom one here if you need an extra step.');
    }
}
