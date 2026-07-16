<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('locale', 5)->default('en');
            // 'Article' | 'Page' — همان قرارداد رشته‌ی کوتاهِ نگاشت چندریختی (نه FQCN)؛ ممکن است
            // خالی بماند تا وقتی که هنوز یک Idea محض است و نوع محتوا مشخص نشده
            $table->string('content_type', 50)->nullable();
            // اتصال چندریختی به Article/Page واقعی — تا رسیدن به مرحله‌ی AI Draft خالی می‌ماند
            // (نگاه کنید به App\Models\ContentPlan::materializeContent())
            $table->string('contentable_type', 100)->nullable();
            $table->unsignedBigInteger('contentable_id')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('workflow_stage_id')->constrained()->restrictOnDelete();
            $table->string('priority', 20)->default('medium');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            // تاریخ انتشارِ برنامه‌ریزی‌شده برای زمانی که هنوز contentable ساخته نشده — وقتی
            // contentable وجود دارد، تقویم از published_at خودِ آن استفاده می‌کند، نه این ستون
            $table->timestamp('planned_publish_at')->nullable();
            $table->timestamp('due_at')->nullable();
            // وضعیت تیک‌خورده‌ی چک‌لیستِ هر مرحله: {"seo_review": {"meta_title": true, ...}}
            $table->json('checklist_state')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['contentable_type', 'contentable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plans');
    }
};
