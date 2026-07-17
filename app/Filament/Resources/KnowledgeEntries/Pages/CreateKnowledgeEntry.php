<?php

namespace App\Filament\Resources\KnowledgeEntries\Pages;

use App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource;
use App\Models\KnowledgeEntryAttachment;
use Filament\Resources\Pages\CreateRecord;

class CreateKnowledgeEntry extends CreateRecord
{
    protected static string $resource = KnowledgeEntryResource::class;

    private array $newAttachmentPaths = [];

    private ?string $newWebsiteUrl = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->newAttachmentPaths = $data['new_attachments'] ?? [];
        unset($data['new_attachments']);

        $this->newWebsiteUrl = $data['new_website_url'] ?? null;
        unset($data['new_website_url']);

        return $data;
    }

    protected function afterCreate(): void
    {
        KnowledgeEntryAttachment::createManyFromDiskPaths($this->record, $this->newAttachmentPaths);

        if (filled($this->newWebsiteUrl)) {
            KnowledgeEntryAttachment::createFromUrl($this->record, $this->newWebsiteUrl);
        }
    }
}
