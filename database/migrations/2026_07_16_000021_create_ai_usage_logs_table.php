<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            // denormalized عمدی — اگر بعداً ردیف ai_provider_configs حذف شود، تاریخچه‌ی مصرف باید
            // همچنان بخوانا بماند (بدون کلید خارجی به آن جدول)
            $table->string('provider_slug', 50);
            $table->string('model')->nullable();
            $table->string('action_key', 50)->nullable();
            // همان قرارداد رشته‌های کوتاه Article/Page که در ai_generations/keywords استفاده می‌شود
            $table->string('content_type', 50)->nullable();
            $table->unsignedBigInteger('content_id')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('estimated_cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            // success | failed
            $table->string('status', 20);
            // پیام خطای پاک‌سازی‌شده — هرگز کلید API یا هدرهای درخواست را در اینجا ذخیره نکنید
            $table->text('error_message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['provider_slug', 'created_at']);
            $table->index('action_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
