<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_link_suggestions', function (Blueprint $table) {
            $table->id();
            // مقاله/صفحه‌ای که پیشنهاد می‌شود لینک را اضافه کند
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            // مقاله/صفحه‌ای که پیشنهاد می‌شود لینک به آن داده شود
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            // تکراری نگه‌داشته‌شده از source/target برای فیلتر سریع در داشبورد — بدون این، فیلتر بر اساس
            // زبان نیازمند join به دو جدول مختلف (articles/pages) می‌شد
            $table->string('locale');
            $table->unsignedTinyInteger('confidence_score');
            $table->string('recommended_anchor_text');
            $table->text('reason');
            // pending | approved | dismissed — بازتولید پیشنهادها هرگز ردیف‌های approved/dismissed را دست نمی‌زند
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'target_type', 'target_id'], 'internal_link_suggestions_pair_unique');
            $table->index(['status', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_link_suggestions');
    }
};
