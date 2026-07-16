<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک واحد دانش ساخت‌یافته درباره‌ی برند/کسب‌وکار — قبل از تولید محتوا توسط دستیار هوش
    // مصنوعی بازیابی و به پرامپت اضافه می‌شود (نگاه کنید به App\Services\KnowledgeBase\KnowledgeBaseService).
    // بر خلاف Brand Memory (که یک بلوک ثابت همیشه‌حاضر است)، این‌جا هر بار فقط ورودی‌های واقعاً
    // مرتبط با همان تولید انتخاب می‌شوند — همان چیزی که «semantic retrieval» در درخواست یعنی.
    public function up(): void
    {
        Schema::create('knowledge_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            // رشته‌ی آزاد با datalist از دسته‌های پیشنهادی در UI — همان الگوی BrandMemorySection.group
            $table->string('category', 100);
            // فقط en/tr — بر خلاف BrandMemoryValue که fa هم دارد؛ این محتوا مستقیماً وارد تولید
            // محتوای واقعی سایت می‌شود (که فقط en/tr است)، نه صرفاً یک بلوک مرجع ثابت
            $table->string('locale', 5)->default('en');
            $table->longText('content');
            $table->string('source')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('priority', 20)->default('medium');
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['locale', 'status']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entries');
    }
};
