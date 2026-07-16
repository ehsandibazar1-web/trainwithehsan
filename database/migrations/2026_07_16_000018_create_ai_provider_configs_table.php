<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_configs', function (Blueprint $table) {
            $table->id();
            // شناسه‌ی ثابت ارائه‌دهنده — کلید انتخاب کلاس Provider در ProviderManager (طول ۵۰ عمدی
            // است، طبق همان درسِ خطای «Specified key was too long» روی MySQL که در ai_generations
            // پیش آمد؛ نگاه کنید به CLAUDE.md)
            $table->string('slug', 50)->unique();
            $table->string('name');
            // کلید API رمزنگاری‌شده (کست encrypted در مدل) — اولین استفاده‌ی رمزنگاری در این پروژه؛
            // هرگز خام در پاسخ فرم Filament نمایش داده نمی‌شود
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            // مقدار متنی ساده، نه کلید خارجی — فهرست مدل‌های شناخته‌شده در ai_provider_models فقط
            // پیشنهاد را پر می‌کند، نامی هاردکد نمی‌شود
            $table->string('default_model')->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->decimal('temperature', 3, 2)->nullable();
            $table->unsignedInteger('timeout_seconds')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            // success | failed
            $table->string('last_test_status', 20)->nullable();
            $table->unsignedInteger('last_test_latency_ms')->nullable();
            $table->string('last_test_model')->nullable();
            $table->text('last_test_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_configs');
    }
};
