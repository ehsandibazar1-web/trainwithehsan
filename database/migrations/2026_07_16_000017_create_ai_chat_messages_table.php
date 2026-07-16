<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            // همان قرارداد رشته‌های کوتاه ai_generations.content_type — طول ۵۰ عمدی است، طبق همان
            // درسِ خطای «Specified key was too long» روی MySQL (نگاه کنید به CLAUDE.md)
            $table->string('content_type', 50);
            $table->unsignedBigInteger('content_id');
            // user | assistant
            $table->string('role', 20);
            $table->text('message');
            // اگر این پیام یک درخواست عملی بود (نه فقط گفتگو)، به همان ردیف AiGeneration که
            // ساخته لینک می‌شود — برای نمایش وضعیت/نتیجه‌ی تولید داخل رشته‌ی گفتگو
            $table->foreignId('related_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_messages');
    }
};
