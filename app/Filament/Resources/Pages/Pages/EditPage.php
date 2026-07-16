<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

// نمای سفارشی $view: سایدبار AI Assistant تعبیه‌شده — نگاه کنید به
// resources/views/filament/resources/pages/pages/edit-page.blade.php
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    protected string $view = 'filament.resources.pages.pages.edit-page';

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
