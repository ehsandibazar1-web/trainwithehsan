<?php

namespace App\Filament\Resources\KnowledgeEntries\Tables;

use App\Models\KnowledgeEntry;
use App\Models\Tag;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class KnowledgeEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        KnowledgeEntry::STATUS_ACTIVE => 'success',
                        KnowledgeEntry::STATUS_DRAFT => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        KnowledgeEntry::PRIORITY_CRITICAL => 'danger',
                        KnowledgeEntry::PRIORITY_HIGH => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean(),

                TextColumn::make('tags_count')
                    ->label('Tags')
                    ->counts('tags'),

                TextColumn::make('attachments_count')
                    ->label('Files')
                    ->counts('attachments'),

                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('Y-m-d')
                    ->placeholder('—')
                    ->color(fn (?KnowledgeEntry $record): ?string => $record?->isExpired() ? 'danger' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last edited')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->label('Language')
                    ->options(['en' => 'English', 'tr' => 'Türkçe']),

                SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn () => KnowledgeEntry::query()->whereNotNull('category')->distinct()->pluck('category', 'category')->all()),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        KnowledgeEntry::STATUS_DRAFT => 'Draft',
                        KnowledgeEntry::STATUS_ACTIVE => 'Active',
                        KnowledgeEntry::STATUS_ARCHIVED => 'Archived',
                    ]),

                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options([
                        KnowledgeEntry::PRIORITY_LOW => 'Low',
                        KnowledgeEntry::PRIORITY_MEDIUM => 'Medium',
                        KnowledgeEntry::PRIORITY_HIGH => 'High',
                        KnowledgeEntry::PRIORITY_CRITICAL => 'Critical',
                    ]),

                SelectFilter::make('tags')
                    ->label('Tag')
                    ->relationship('tags', 'name')
                    ->options(fn () => Tag::pluck('name', 'id')),

                TernaryFilter::make('is_pinned')
                    ->label('Pinned'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No knowledge entries yet')
            ->emptyStateDescription('Add facts about the brand/business (biography, services, policies, locations, ...) that the AI Assistant should know before writing content.')
            ->defaultSort('updated_at', 'desc');
    }
}
