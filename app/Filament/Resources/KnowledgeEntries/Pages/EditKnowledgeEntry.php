<?php

namespace App\Filament\Resources\KnowledgeEntries\Pages;

use App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Jobs\IndexKnowledgeContent;
use App\Models\KnowledgeEntryAttachment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditKnowledgeEntry extends EditRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    private array $newAttachmentPaths = [];

    private ?string $newWebsiteUrl = null;

    protected function getHeaderActions(): array
    {
        return [
            // ایندکسِ RAG خودش بعد از هر save (content عوض شده) یا هر پیوستِ تازه دیسپچ می‌شود
            // (نگاه کنید به KnowledgeEntry/KnowledgeEntryAttachment::booted())؛ این دکمه فقط برای
            // بازسازیِ دستیِ همین یک ورودی است — مثلا وقتی ارائه‌دهنده‌ی embedding تازه تنظیم/عوض
            // شده و ادمین می‌خواهد بدون تغییرِ content دوباره embed شود.
            Action::make('reindex')
                ->label('Reindex for AI')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    dispatch(new IndexKnowledgeContent($this->record));

                    foreach ($this->record->attachments as $attachment) {
                        dispatch(new IndexKnowledgeContent($attachment));
                    }

                    Notification::make()
                        ->success()
                        ->title('Reindexing queued')
                        ->body('This entry and its attachments will be re-extracted, chunked, and embedded.')
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->newAttachmentPaths = $data['new_attachments'] ?? [];
        unset($data['new_attachments']);

        $this->newWebsiteUrl = $data['new_website_url'] ?? null;
        unset($data['new_website_url']);

        return $data;
    }

    protected function afterSave(): void
    {
        KnowledgeEntryAttachment::createManyFromDiskPaths($this->record, $this->newAttachmentPaths);

        if (filled($this->newWebsiteUrl)) {
            KnowledgeEntryAttachment::createFromUrl($this->record, $this->newWebsiteUrl);
        }
    }
}
