<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Pages\AiContentAssistant;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aiAssistant')
                ->label('AI Assistant')
                ->icon('heroicon-o-sparkles')
                ->url(fn () => AiContentAssistant::getUrl(['page' => $this->record->id])),
            DeleteAction::make(),
        ];
    }
}
