<?php

namespace App\Filament\Resources\WorkflowStages;

use App\Filament\Resources\WorkflowStages\Pages\CreateWorkflowStage;
use App\Filament\Resources\WorkflowStages\Pages\EditWorkflowStage;
use App\Filament\Resources\WorkflowStages\Pages\ListWorkflowStages;
use App\Filament\Resources\WorkflowStages\Schemas\WorkflowStageForm;
use App\Filament\Resources\WorkflowStages\Tables\WorkflowStagesTable;
use App\Models\WorkflowStage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WorkflowStageResource extends Resource
{
    protected static ?string $model = WorkflowStage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Content Planner';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Workflow Stages';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return WorkflowStageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WorkflowStagesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWorkflowStages::route('/'),
            'create' => CreateWorkflowStage::route('/create'),
            'edit' => EditWorkflowStage::route('/{record}/edit'),
        ];
    }
}
