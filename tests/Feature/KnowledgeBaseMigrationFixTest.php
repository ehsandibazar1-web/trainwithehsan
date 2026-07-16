<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\KnowledgeEntry;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBaseMigrationFixTest extends TestCase
{
    use RefreshDatabase;

    // ستون‌گذاری اصلی این جدول (unique(['ai_generation_id', 'knowledge_entry_id'])) روی MySQL با
    // نام خودکار بیش از ۶۴ کاراکتری شکست می‌خورد (SQLSTATE 42000/1059) — نگاه کنید به
    // 2026_07_17_000000_fix_ai_generation_knowledge_entry_unique_index_name.php. این تست تأیید
    // می‌کند که پس از مهاجرت کامل، واقعاً یک محدودیت unique روی این دو ستون برقرار است.
    public function test_the_pair_of_generation_and_knowledge_entry_is_actually_unique_at_the_database_level(): void
    {
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => 1, 'field' => 'body', 'mode' => 'improve', 'status' => 'completed',
        ]);
        $entry = KnowledgeEntry::create([
            'title' => 'Fact', 'category' => 'Business Information', 'locale' => 'en', 'content' => 'Some fact.',
        ]);

        $generation->knowledgeEntries()->attach($entry->id);

        $this->expectException(QueryException::class);
        $generation->knowledgeEntries()->attach($entry->id);
    }

    // خودِ فایل fix-up migration باید idempotent باشد — اجرای دوباره‌ی up() (مثلاً روی نصبی که
    // migration اصلی هرگز شکسته نخورده و ایندکس از قبل با نام درست وجود دارد) نباید خطا بدهد.
    public function test_the_fixup_migration_is_idempotent_when_the_index_already_exists(): void
    {
        $migration = require database_path('migrations/2026_07_17_000000_fix_ai_generation_knowledge_entry_unique_index_name.php');

        $migration->up();
        $migration->up();

        $this->assertTrue(true);
    }
}
