<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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

    // پس از آپلود توسط Filament\Forms\Components\FileUpload (که فقط مسیرهای دیسک را برمی‌گرداند،
    // نه یک شیء فایل)، برای هر مسیر یک ردیف واقعی می‌سازد — از App\Filament\Resources\KnowledgeEntries\Pages\{Create,Edit}KnowledgeEntry
    // فراخوانی می‌شود تا این منطق یک‌بار نوشته شود، نه در هر دو صفحه
    public static function createManyFromDiskPaths(KnowledgeEntry $entry, array $paths): void
    {
        foreach ($paths as $path) {
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }

            $entry->attachments()->create([
                'disk_path' => $path,
                'original_filename' => basename($path),
                'mime_type' => Storage::disk('public')->mimeType($path) ?: null,
                'size' => Storage::disk('public')->size($path) ?: null,
            ]);
        }
    }
}
