<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Pages\AiContentAssistant;
use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aiAssistant')
                ->label('AI Assistant')
                ->icon('heroicon-o-sparkles')
                ->url(fn () => AiContentAssistant::getUrl(['article' => $this->record->id])),
            DeleteAction::make(),
        ];
    }
}
