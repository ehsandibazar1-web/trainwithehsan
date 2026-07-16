<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // تاریخچه‌ی تغییر مرحله — جدا از activity_log (که App\Models\ContentPlan هم از آن استفاده
    // می‌کند) چون این جدول برای محاسبه‌ی سریع آمار (میانگین زمان انتشار/بازبینی در داشبورد
    // برنامه‌ریز) طراحی شده، نه یک ردپای قابل‌خواندن برای انسان — دقیقاً همان دلیلی که
    // ai_usage_logs کنار activity_log وجود دارد، نه به‌جای آن.
    public function up(): void
    {
        Schema::create('content_plan_stage_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('to_stage_id')->nullable()->constrained('workflow_stages')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['content_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_stage_transitions');
    }
};
