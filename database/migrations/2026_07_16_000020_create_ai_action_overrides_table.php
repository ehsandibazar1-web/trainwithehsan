<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_overrides', function (Blueprint $table) {
            $table->id();
            // کلید فیلد در App\Services\AiAssistant\ActionRegistry (seo_title, faq, translate, ...) —
            // نبودِ ردیف برای یک کلید یعنی «از ارائه‌دهنده‌ی پیش‌فرض استفاده کن»
            $table->string('action_key', 50)->unique();
            $table->foreignId('ai_provider_config_id')->constrained()->cascadeOnDelete();
            // اختیاری — فقط مدل را override می‌کند، در غیر این صورت default_model ارائه‌دهنده استفاده می‌شود
            $table->string('model')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_overrides');
    }
};
