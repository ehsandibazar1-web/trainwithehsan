<?php

namespace App\Filament\Resources\WorkflowStages\Pages;

use App\Filament\Resources\WorkflowStages\WorkflowStageResource;
use App\Models\WorkflowStage;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkflowStage extends CreateRecord
{
    protected static string $resource = WorkflowStageResource::class;

    // فقط یک مرحله می‌تواند is_default باشد — انتخاب این یکی، بقیه را خاموش می‌کند
    protected function afterCreate(): void
    {
        if ($this->record->is_default) {
            WorkflowStage::where('id', '!=', $this->record->id)->update(['is_default' => false]);
        }
    }
}
