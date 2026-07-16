<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeEntryAttachment extends Model
{
    protected $fillable = ['knowledge_entry_id', 'disk_path', 'original_filename', 'mime_type', 'size'];

    public function knowledgeEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->disk_path);
    }
}
