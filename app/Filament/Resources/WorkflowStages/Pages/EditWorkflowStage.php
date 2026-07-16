<?php

namespace App\Filament\Resources\WorkflowStages\Pages;

use App\Filament\Resources\WorkflowStages\WorkflowStageResource;
use App\Models\WorkflowStage;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkflowStage extends EditRecord
{
    protected static string $resource = WorkflowStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => ! $this->record->contentPlans()->exists()),
        ];
    }

    // فقط یک مرحله می‌تواند is_default باشد — انتخاب این یکی، بقیه را خاموش می‌کند
    protected function afterSave(): void
    {
        if ($this->record->is_default) {
            WorkflowStage::where('id', '!=', $this->record->id)->update(['is_default' => false]);
        }
    }
}
