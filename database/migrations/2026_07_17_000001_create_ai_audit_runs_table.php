<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک اجرای AI Agent — دستی («Run Audit Now» در داشبورد) یا زمان‌بندی‌شده (agent:audit، هفتگی).
    // مشابه ImportLog: یک ردیف تاریخچه به‌ازای هر اجرا، نه فقط آخرین نتیجه.
    public function up(): void
    {
        Schema::create('ai_audit_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger_type'); // manual | scheduled
            $table->string('status')->default('running'); // running | completed | failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('found_count')->default(0); // کل یافته‌های این اجرا
            $table->unsignedInteger('new_count')->default(0); // یافته‌های تازه (قبلاً pending نبودند)
            $table->unsignedInteger('resolved_count')->default(0); // یافته‌های pending قبلی که دیگر تکرار نشدند
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_runs');
    }
};
