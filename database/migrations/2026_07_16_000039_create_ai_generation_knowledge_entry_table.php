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

            $table->unique(['ai_generation_id', 'knowledge_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generation_knowledge_entry');
    }
};
