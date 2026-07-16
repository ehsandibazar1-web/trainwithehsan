<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// روی نصب‌هایی که migration اصلی (create_ai_generation_knowledge_entry_table) را پیش از این
// تصحیح اجرا کردند: خودِ CREATE TABLE موفق شد ولی افزودن unique index با نام خودکارِ بیش از
// ۶۴ کاراکتری با خطای SQLSTATE 42000/1059 شکست خورد؛ چون این خطا «table already exists» نیست،
// مکانیزم self-heal در SystemMaintenance آن را نمی‌گیرد و صرفاً به‌عنوان «Migration failed»
// نمایش داده می‌شود. این migration جداگانه و idempotent است — دقیقاً همان الگوی fix-up migration
// که قبلاً برای notifications انجام شد — تا این ایندکس گمشده را با نام کوتاهِ صحیح اضافه کند،
// چه جدول از این migration ساخته شده باشد چه هنوز اصلاً وجود نداشته باشد (نصب کاملاً تازه).
return new class extends Migration
{
    private const INDEX_NAME = 'ai_gen_knowledge_entry_unique';

    public function up(): void
    {
        if (! Schema::hasTable('ai_generation_knowledge_entry')) {
            return;
        }

        if ($this->indexExists('ai_generation_knowledge_entry', self::INDEX_NAME)) {
            return;
        }

        Schema::table('ai_generation_knowledge_entry', function ($table) {
            $table->unique(['ai_generation_id', 'knowledge_entry_id'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_generation_knowledge_entry') && $this->indexExists('ai_generation_knowledge_entry', self::INDEX_NAME)) {
            Schema::table('ai_generation_knowledge_entry', function ($table) {
                $table->dropUnique(self::INDEX_NAME);
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            return collect($connection->select("PRAGMA index_list(\"{$table}\")"))->contains('name', $indexName);
        }

        return count($connection->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName])) > 0;
    }
};
