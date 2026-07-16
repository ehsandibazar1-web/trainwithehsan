<?php

namespace App\Filament\Resources\ContentPlans\Schemas;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\ContentPlan;
use App\Models\ContentTask;
use App\Models\User;
use App\Models\WorkflowStage;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class ContentPlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Title')
                            ->required()
                            ->columnSpanFull(),

                        Select::make('locale')
                            ->label('Language / Dil')
                            ->options(['en' => 'English', 'tr' => 'Türkçe'])
                            ->default('en')
                            ->required(),

                        Select::make('content_type')
                            ->label('Content type')
                            ->options(['Article' => 'Article (blog)', 'Page' => 'Page (standalone)'])
                            ->nullable()
                            ->helperText('What this idea becomes once it reaches AI Draft — leave blank to decide later.'),

                        TextInput::make('category')
                            ->label('Category')
                            ->nullable(),

                        Select::make('priority')
                            ->label('Priority')
                            ->options([
                                ContentPlan::PRIORITY_LOW => 'Low',
                                ContentPlan::PRIORITY_MEDIUM => 'Medium',
                                ContentPlan::PRIORITY_HIGH => 'High',
                                ContentPlan::PRIORITY_CRITICAL => 'Critical',
                            ])
                            ->default(ContentPlan::PRIORITY_MEDIUM)
                            ->required(),

                        Select::make('workflow_stage_id')
                            ->label('Workflow stage')
                            ->options(fn () => WorkflowStage::orderBy('sort_order')->pluck('label', 'id'))
                            ->default(fn () => WorkflowStage::default()?->id)
                            ->required()
                            ->helperText('Move this from the Kanban board for automatic behavior (draft creation, notifications) — changing it here works too, just without an "actor" recorded.'),

                        Select::make('tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Tags')
                            ->createOptionForm([
                                TextInput::make('name')->label('Tag name')->required(),
                            ]),

                        Placeholder::make('contentable_link')
                            ->label('Linked content')
                            ->columnSpanFull()
                            ->visible(fn (?ContentPlan $record): bool => $record !== null)
                            ->content(function (?ContentPlan $record) {
                                if (! $record?->contentable) {
                                    return 'Not created yet — reaching the AI Draft stage will auto-create a draft Article/Page from this idea.';
                                }

                                $url = $record->contentable_type === 'Page'
                                    ? PageResource::getUrl('edit', ['record' => $record->contentable_id])
                                    : ArticleResource::getUrl('edit', ['record' => $record->contentable_id]);

                                return new HtmlString('<a href="'.e($url).'" class="underline">'.e($record->contentable->title).' →</a>');
                            }),
                    ]),

                Section::make('Ownership & scheduling')
                    ->columns(2)
                    ->schema([
                        Select::make('author_id')
                            ->label('Author')
                            ->options(fn () => User::pluck('name', 'id'))
                            ->searchable()
                            ->nullable(),

                        Select::make('assigned_to')
                            ->label('Assigned to')
                            ->options(fn () => User::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Receives notifications for this card (falls back to the author if left blank).'),

                        DateTimePicker::make('planned_publish_at')
                            ->label('Planned publish date')
                            ->nullable()
                            ->helperText('Only used until a real Article/Page is linked — the calendar then follows that record\'s own publish date instead.'),

                        DateTimePicker::make('due_at')
                            ->label('Draft deadline')
                            ->nullable()
                            ->helperText('Triggers a deadline-approaching notification within 24 hours of this time.'),
                    ]),

                Section::make('Tasks')
                    ->schema([
                        Repeater::make('tasks')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->label('Tasks')
                            ->schema([
                                TextInput::make('title')
                                    ->label('Task')
                                    ->required()
                                    ->columnSpanFull(),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        ContentTask::STATUS_PENDING => 'Pending',
                                        ContentTask::STATUS_IN_PROGRESS => 'In progress',
                                        ContentTask::STATUS_DONE => 'Done',
                                    ])
                                    ->default(ContentTask::STATUS_PENDING)
                                    ->required(),

                                DateTimePicker::make('due_at')
                                    ->label('Due date')
                                    ->nullable(),

                                Select::make('assigned_to')
                                    ->label('Assigned to')
                                    ->options(fn () => User::pluck('name', 'id'))
                                    ->searchable()
                                    ->nullable(),

                                Textarea::make('notes')
                                    ->label('Notes')
                                    ->rows(2)
                                    ->nullable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->addActionLabel('Add task')
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                            ->defaultItems(0)
                            ->collapsible()
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),

                Section::make('Stage checklist')
                    ->visible(fn (?ContentPlan $record): bool => $record !== null && filled($record->workflowStage?->checklist_items))
                    ->schema(function (?ContentPlan $record) {
                        $stage = $record?->workflowStage;

                        if (! $stage || blank($stage->checklist_items)) {
                            return [];
                        }

                        return collect($stage->checklist_items)
                            ->map(fn (array $item) => Checkbox::make("checklist_state.{$stage->slug}.{$item['key']}")->label($item['label']))
                            ->all();
                    })
                    ->columns(2),

                Grid::make(1)->schema([
                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->nullable(),
                ]),
            ]);
    }
}
