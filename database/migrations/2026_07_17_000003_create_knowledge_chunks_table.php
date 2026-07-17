<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک قطعه‌ی قابل‌بازیابیِ متن + بردار embedding آن — App\Services\Rag\Contracts\VectorStore
    // تنها مصرف‌کننده‌ی این جدول است (بقیه‌ی کد فقط با آن اینترفیس کار می‌کند، نه مستقیم با این
    // جدول) تا جایگزینیِ بعدیِ ذخیره‌سازی برداری (مثلاً pgvector) فقط به تغییر پیاده‌سازیِ
    // VectorStore نیاز داشته باشد، نه به تغییر منطق کسب‌وکار — نگاه کنید به CLAUDE.md.
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            // منبع این قطعه — یک KnowledgeEntry (فیلد content خودش) یا یک KnowledgeEntryAttachment
            // (متن استخراج‌شده از فایل/صفحه‌ی وب)
            $table->string('chunkable_type', 50);
            $table->unsignedBigInteger('chunkable_id');
            // KnowledgeEntry مالکِ نهایی — حتی وقتی chunkable یک attachment است، برای فیلتر/join سریع
            // بدون عبور از رابطه؛ denormalized عمدی، هم‌روح ai_generations.content_type/content_id
            $table->foreignId('knowledge_entry_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('text');
            $table->unsignedInteger('char_count');
            $table->json('embedding');
            $table->string('embedding_model');
            $table->unsignedInteger('embedding_dims');
            $table->string('locale', 5)->nullable();
            $table->timestamps();

            $table->unique(['chunkable_type', 'chunkable_id', 'chunk_index'], 'knowledge_chunk_unique');
            $table->index(['knowledge_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
