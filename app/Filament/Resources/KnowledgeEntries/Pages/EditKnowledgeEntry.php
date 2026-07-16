<?php

namespace App\Filament\Resources\KnowledgeEntries\Pages;

use App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntryAttachment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKnowledgeEntry extends EditRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    private array $newAttachmentPaths = [];

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->newAttachmentPaths = $data['new_attachments'] ?? [];
        unset($data['new_attachments']);

        return $data;
    }

    protected function afterSave(): void
    {
        KnowledgeEntryAttachment::createManyFromDiskPaths($this->record, $this->newAttachmentPaths);
    }
}
