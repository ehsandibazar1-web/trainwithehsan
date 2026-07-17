<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // هم‌روحِ 2026_07_17_000004 (embedding) — دو ستون کوچکِ افزودنی برای فعال‌سازیِ تولید تصویر با
    // هوش مصنوعی، بدون تغییر جدول‌های موجود دیگر: (۱) کدام مدلِ این ارائه‌دهنده برای تولید تصویر
    // استفاده شود (فقط OpenAI/Gemini واقعاً API تولید تصویر دارند)، (۲) ارائه‌دهنده‌ی پیش‌فرض/failover
    // فعلیِ تولید تصویر — دقیقاً همان سه‌تاییِ default/failover_enabled/fallback که برای متن هم
    // استفاده شده (طبق درخواستِ صریح کاربر: «یک معماریِ کاملاً مشابهِ ارائه‌دهنده‌ی متنی»)
    public function up(): void
    {
        Schema::table('ai_provider_configs', function (Blueprint $table) {
            $table->string('image_model')->nullable()->after('embedding_model');
        });

        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->foreignId('default_image_provider_config_id')->nullable()->after('embedding_provider_config_id')
                ->constrained('ai_provider_configs')->nullOnDelete();
            $table->boolean('image_failover_enabled')->default(false)->after('default_image_provider_config_id');
            $table->foreignId('fallback_image_provider_config_id')->nullable()->after('image_failover_enabled')
                ->constrained('ai_provider_configs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_provider_settings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('fallback_image_provider_config_id');
            $table->dropColumn('image_failover_enabled');
            $table->dropConstrainedForeignId('default_image_provider_config_id');
        });

        Schema::table('ai_provider_configs', function (Blueprint $table) {
            $table->dropColumn('image_model');
        });
    }
};
