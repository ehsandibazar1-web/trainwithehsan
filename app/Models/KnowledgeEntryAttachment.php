<?php

namespace App\Models;

use App\Jobs\IndexKnowledgeContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class KnowledgeEntryAttachment extends Model
{
    public const SOURCE_FILE = 'file';

    public const SOURCE_URL = 'url';

    public const EXTRACTION_PENDING = 'pending';

    public const EXTRACTION_EXTRACTED = 'extracted';

    public const EXTRACTION_FAILED = 'failed';

    protected $fillable = [
        'knowledge_entry_id', 'disk_path', 'original_filename', 'mime_type', 'size',
        'source_type', 'source_url', 'extraction_status', 'extracted_text', 'extracted_at', 'extraction_error',
    ];

    protected $attributes = [
        'source_type' => self::SOURCE_FILE,
        'extraction_status' => self::EXTRACTION_PENDING,
    ];

    protected function casts(): array
    {
        return [
            'extracted_at' => 'datetime',
        ];
    }

    // فقط روی created() صف می‌شود، نه هر save() — App\Services\Rag\IndexingService::indexAttachment()
    // خودش روی همین رکورد save() می‌زند (extraction_status/extracted_text) تا استخراج را ثبت کند؛
    // اگر این قلاب روی updated() هم فعال بود، همان save() یک چرخه‌ی بی‌پایان از دیسپچ می‌ساخت.
    protected static function booted(): void
    {
        static::created(function (KnowledgeEntryAttachment $attachment) {
            dispatch(new IndexKnowledgeContent($attachment));
        });
    }

    public function knowledgeEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeEntry::class);
    }

    public function chunks(): MorphMany
    {
        return $this->morphMany(KnowledgeChunk::class, 'chunkable');
    }

    public function isUrlSource(): bool
    {
        return $this->source_type === self::SOURCE_URL;
    }

    public function url(): string
    {
        if ($this->isUrlSource()) {
            return (string) $this->source_url;
        }

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

    // «صفحه‌ی وب» — پیوستی بدون فایل روی دیسک، فقط یک URL که
    // App\Services\Rag\TextExtractionService بعداً واکشی/استخراج می‌کند. disk_path رشته‌ی خالی
    // است نه NULL (ستون موجود nullable نیست، تغییرش نیازمند doctrine/dbal است — نگاه کنید به
    // migration مربوطه).
    public static function createFromUrl(KnowledgeEntry $entry, string $url): self
    {
        return $entry->attachments()->create([
            'disk_path' => '',
            'original_filename' => $url,
            'source_type' => self::SOURCE_URL,
            'source_url' => $url,
        ]);
    }
}
