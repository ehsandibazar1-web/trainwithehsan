<?php

namespace App\Filament\Resources\ContentPlans\Pages;

use App\Filament\Resources\ContentPlans\ContentPlanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContentPlan extends EditRecord
{
    protected static string $resource = ContentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
