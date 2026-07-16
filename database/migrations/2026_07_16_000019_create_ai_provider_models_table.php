<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_provider_config_id')->constrained()->cascadeOnDelete();
            // برچسب خوانا برای ادمین («Claude Sonnet 4.5») در برابر شناسه‌ی واقعی مدل که در
            // درخواست API فرستاده می‌شود («claude-sonnet-4-5») — عمداً هیچ نامی در کد هاردکد نیست،
            // این جدول همان کاتالوگِ مدل‌هایی است که ادمین خودش نگه می‌دارد
            $table->string('label');
            $table->string('model');
            // اختیاری — برای محاسبه‌ی estimated_cost_usd در ai_usage_logs؛ اگر خالی بماند هزینه
            // محاسبه نمی‌شود (نه یک عدد حدسی)
            $table->decimal('input_price_per_million', 10, 4)->nullable();
            $table->decimal('output_price_per_million', 10, 4)->nullable();
            $table->timestamps();

            $table->unique(['ai_provider_config_id', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_models');
    }
};
