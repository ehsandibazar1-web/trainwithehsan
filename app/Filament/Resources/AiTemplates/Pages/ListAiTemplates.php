<?php

namespace App\Filament\Resources\AiTemplates\Pages;

use App\Filament\Resources\AiTemplates\AiTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiTemplates extends ListRecords
{
    protected static string $resource = AiTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
