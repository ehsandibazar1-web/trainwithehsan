<?php

namespace App\Filament\Resources\AiProfiles\Pages;

use App\Filament\Resources\AiProfiles\AiProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAiProfile extends EditRecord
{
    protected static string $resource = AiProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
