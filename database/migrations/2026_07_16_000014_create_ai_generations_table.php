<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // مقاله/صفحه‌ای که این تولید برایش انجام شده — همان قرارداد رشته‌های کوتاه
            // ('Article'/'Page') که در keywords/internal_link_suggestions هم استفاده می‌شود.
            // طول ۵۰ عمدی است: این دو ستون بخشی از ایندکس ترکیبی زیرند و VARCHAR(255) پیش‌فرض
            // با utf8mb4 روی MySQL از سقف طول کلید ایندکس رد می‌شود (خطای واقعی روی production)
            $table->string('content_type', 50);
            $table->unsignedBigInteger('content_id');
            // کلید فیلد در App\Services\AiAssistant\ActionRegistry (seo_title, faq, tags, ...)
            $table->string('field', 50);
            // generate | improve | rewrite | expand | shorten | simplify
            $table->string('mode');
            $table->string('provider')->nullable();
            // queued | processing | completed | failed
            $table->string('status')->default('queued');
            // مقدار فعلی فیلد درست قبل از این اجرا — برای Restore لازم است
            $table->json('input_snapshot')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restored_at')->nullable();
            $table->foreignId('restored_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type', 'content_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_generations');
    }
};
