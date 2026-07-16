<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // جدول تک‌ردیفه‌ی تنظیمات سراسری Provider Manager (همیشه فقط id=1) — برای دو/سه مقدار
        // سراسری (پیش‌فرض، failover) یک جدول singleton مرسوم‌تر و صریح‌تر از اضافه‌کردن این
        // مقادیر به SiteSetting (که برای محتوای صفحات عمومی است، نه تنظیمات این ماژول) یا
        // نشستن روی یکی از ردیف‌های ai_provider_configs (که وضعیت «پیش‌فرض کدام است» را
        // مبهم می‌کرد اگر دو ردیف هم‌زمان is_default=true می‌شدند) است
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('default_provider_config_id')->nullable()->constrained('ai_provider_configs')->nullOnDelete();
            $table->boolean('failover_enabled')->default(false);
            $table->foreignId('fallback_provider_config_id')->nullable()->constrained('ai_provider_configs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
