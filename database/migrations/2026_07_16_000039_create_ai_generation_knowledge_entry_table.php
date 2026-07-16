<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ثبت اینکه کدام ورودی‌های دانش برای یک اجرای مشخصِ تولید هوش مصنوعی واقعاً استفاده شدند —
    // پایه‌ی نمایش «Knowledge used» زیر هر تولید در AiAssistantPanel. یک pivot ساده، هم‌شکل با
    // taggables (id + دو کلید خارجی + timestamps).
    public function up(): void
    {
        Schema::create('ai_generation_knowledge_entry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_generation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_entry_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // نام صریح و کوتاه — نام خودکار Laravel برای این جفت ستون از سقف ۶۴ کاراکتریِ
            // MySQL برای نام شناسه‌ها عبور می‌کند (SQLSTATE 42000 / 1059)؛ همان درسی که قبلاً برای
            // internal_link_suggestions گرفته شده بود، اینجا فراموش شده بود — نگاه کنید به
            // migration بعدی برای نصب‌هایی که این نسخه‌ی شکسته را قبلاً اجرا کرده‌اند
            $table->unique(['ai_generation_id', 'knowledge_entry_id'], 'ai_gen_knowledge_entry_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_knowledge_entry');
    }
};
