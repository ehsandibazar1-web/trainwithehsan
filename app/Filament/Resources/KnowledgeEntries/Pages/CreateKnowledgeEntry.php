<?php

namespace App\Filament\Resources\KnowledgeEntries\Pages;

use App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntryAttachment;
use Filament\Resources\Pages\CreateRecord;

class CreateKnowledgeEntry extends CreateRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    private array $newAttachmentPaths = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->newAttachmentPaths = $data['new_attachments'] ?? [];
        unset($data['new_attachments']);

        return $data;
    }

    protected function afterCreate(): void
    {
        KnowledgeEntryAttachment::createManyFromDiskPaths($this->record, $this->newAttachmentPaths);
    }
}
