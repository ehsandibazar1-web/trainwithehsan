<?php

namespace App\Filament\Resources\WorkflowStages\Schemas;

use App\Models\WorkflowStage;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class WorkflowStageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Stage name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug($state ?? '', '_'));
                        }
                    }),

                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->helperText(fn (?WorkflowStage $record) => in_array($record?->slug, [
                        WorkflowStage::STAGE_IDEA, WorkflowStage::STAGE_AI_DRAFT, WorkflowStage::STAGE_HUMAN_REVIEW,
                        WorkflowStage::STAGE_SEO_REVIEW, WorkflowStage::STAGE_SCHEDULED, WorkflowStage::STAGE_PUBLISHED,
                        WorkflowStage::STAGE_ARCHIVED,
                    ], true)
                        ? 'This slug is used by automatic behavior (materializing a draft, syncing with publishing, notifications) — changing it disables that behavior for this stage.'
                        : 'Used internally — letters, numbers and underscores only.'),

                TextInput::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first on the Kanban board. You can also drag to reorder from the list.'),

                ColorPicker::make('color')
                    ->label('Color')
                    ->nullable()
                    ->helperText('Used for this stage\'s Kanban column and calendar badges.'),

                Toggle::make('is_default')
                    ->label('Default stage for new cards')
                    ->helperText('Only one stage can be the default — choosing this one will unset it on any other stage.'),

                Toggle::make('is_terminal')
                    ->label('Terminal stage')
                    ->helperText('Marks this as an end state (e.g. Archived) — purely informational, does not block further moves.'),

                Section::make('Checklist')
                    ->description('Shown on every content plan card while it\'s in this stage — e.g. SEO Review\'s Meta Title / FAQ / Internal Links checks.')
                    ->schema([
                        Repeater::make('checklist_items')
                            ->label('Checklist items')
                            ->schema([
                                TextInput::make('key')
                                    ->label('Key')
                                    ->required()
                                    ->helperText('Internal identifier, e.g. "meta_title".'),
                                TextInput::make('label')
                                    ->label('Label')
                                    ->required()
                                    ->helperText('Shown to the admin, e.g. "Meta Title".'),
                            ])
                            ->columns(2)
                            ->addActionLabel('Add checklist item')
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                            ->defaultItems(0)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }
}
