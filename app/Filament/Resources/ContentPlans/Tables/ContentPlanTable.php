<?php

namespace App\Filament\Resources\ContentPlans\Tables;

use App\Models\ContentPlan;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\AiAssistant\ContentReviewService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * تعریف ستون‌ها/فیلترها یک‌بار اینجا نوشته شده و هم توسط ContentPlanResource و هم توسط تب
 * Table برنامه‌ریز محتوا (App\Filament\Pages\ContentPlanner) استفاده می‌شود — بدون تکرار.
 */
class ContentPlanTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),

                TextColumn::make('category')
                    ->label('Category')
                    ->default('—')
                    ->toggleable(),

                TextColumn::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->default('—'),

                TextColumn::make('assignee.name')
                    ->label('Assigned to')
                    ->default('—'),

                TextColumn::make('workflowStage.label')
                    ->label('Stage')
                    ->badge()
                    ->color(fn (ContentPlan $record): string => $record->workflowStage?->color ?? 'gray'),

                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'low' => 'gray',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),

                TextColumn::make('seo_score')
                    ->label('SEO Score')
                    ->state(fn (ContentPlan $record): ?int => self::scoreCardFor($record)['categories']['seo']['score'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('ai_score')
                    ->label('AI Score')
                    ->state(fn (ContentPlan $record): ?int => self::scoreCardFor($record)['overall'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('readability_score')
                    ->label('Readability')
                    ->state(fn (ContentPlan $record): ?int => self::scoreCardFor($record)['categories']['readability']['score'] ?? null)
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('publish_date')
                    ->label('Publish date')
                    ->state(fn (ContentPlan $record) => $record->effectivePublishDate())
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—'),

                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('workflow_stage_id')
                    ->label('Workflow')
                    ->options(fn () => WorkflowStage::orderBy('sort_order')->pluck('label', 'id')),

                SelectFilter::make('locale')
                    ->label('Language')
                    ->options(['en' => 'English', 'tr' => 'Türkçe']),

                SelectFilter::make('author_id')
                    ->label('Author')
                    ->options(fn () => User::pluck('name', 'id')),

                SelectFilter::make('category')
                    ->label('Category')
                    ->options(fn () => ContentPlan::whereNotNull('category')->distinct()->pluck('category', 'category')->all()),

                SelectFilter::make('tags')
                    ->label('Tag')
                    ->relationship('tags', 'name')
                    ->options(fn () => Tag::pluck('name', 'id')),

                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                        'critical' => 'Critical',
                    ]),

                SelectFilter::make('contentable_type')
                    ->label('Publication status')
                    ->options([
                        'none' => 'No content yet',
                        'Article' => 'Article',
                        'Page' => 'Page',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            null, '' => $query,
                            'none' => $query->whereNull('contentable_type'),
                            default => $query->where('contentable_type', $value),
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->emptyStateHeading('No content plans yet')
            ->emptyStateDescription('Add an idea to start the pipeline — it doesn\'t need a real Article/Page until it reaches AI Draft.');
    }

    private static function scoreCardFor(ContentPlan $record): array
    {
        if (! $record->contentable) {
            return [];
        }

        return app(ContentReviewService::class)->scoreCard($record->contentable);
    }
}
