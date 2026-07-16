<?php

namespace App\Filament\Resources\AiProviderConfigs\Pages;

use App\Filament\Resources\AiProviderConfigs\AiProviderConfigResource;
use Filament\Resources\Pages\ListRecords;

class ListAiProviderConfigs extends ListRecords
{
    protected static string $resource = AiProviderConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
