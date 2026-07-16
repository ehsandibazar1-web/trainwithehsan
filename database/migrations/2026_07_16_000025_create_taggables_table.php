<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // pivot چندریختیِ many-to-many بین Tag و هر محتوایی (Article/Page و بعداً ContentPlan) —
        // taggable_type به‌صورت صریح varchar(100) تعریف شده (نه پیش‌فرض morphs()) تا ایندکس
        // ترکیبی زیر روی MySQL به خطای «Specified key was too long» نخورد — همان درسی که قبلاً
        // در activity_log/ai_generations اعمال شد (نگاه کنید به CLAUDE.md)
        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('taggable_type', 100);
            $table->unsignedBigInteger('taggable_id');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
