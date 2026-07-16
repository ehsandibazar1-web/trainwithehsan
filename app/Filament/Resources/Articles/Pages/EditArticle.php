<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

// نمای سفارشی $view: سایدبار AI Assistant تعبیه‌شده — نگاه کنید به
// resources/views/filament/resources/articles/pages/edit-article.blade.php
class EditArticle extends EditRecord
{
    protected static string $resource = ArticleResource::class;

    protected string $view = 'filament.resources.articles.pages.edit-article';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aiAssistant')
                ->label('AI Assistant')
                ->icon('heroicon-o-sparkles')
                ->action(fn () => $this->dispatch('toggle-ai-sidebar')),
            DeleteAction::make(),
        ];
    }
}
