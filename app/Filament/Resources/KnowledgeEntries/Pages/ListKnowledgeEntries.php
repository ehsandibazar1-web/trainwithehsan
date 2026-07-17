<?php

namespace App\Filament\Resources\KnowledgeEntries\Pages;

use App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Jobs\RebuildKnowledgeIndex;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListKnowledgeEntries extends ListRecords
{
    protected static string $resource = KnowledgeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // App\Jobs\RebuildKnowledgeIndex — استخراج/تکه‌تکه/embed دوباره‌ی همه‌ی
            // KnowledgeEntry/KnowledgeEntryAttachment ها؛ برای وقتی ارائه‌دهنده‌ی embedding تازه
            // تنظیم شده (بردارهای مدل قدیمی با مدل تازه قابل‌مقایسه نیستند) یا صرفاً برای
            // اطمینان از این‌که همه‌چیز ایندکس شده — صف‌شده چون روی کل کتابخانه اجرا می‌شود.
            Action::make('rebuildAllIndexes')
                ->label('Rebuild All Indexes')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Re-extracts, re-chunks, and re-embeds every Knowledge Base entry and attachment. Runs in the background — this can take a while for a large library.')
                ->action(function (): void {
                    RebuildKnowledgeIndex::dispatch();

                    Notification::make()
                        ->success()
                        ->title('Rebuild queued')
                        ->body('The full Knowledge Base index is being rebuilt in the background.')
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
