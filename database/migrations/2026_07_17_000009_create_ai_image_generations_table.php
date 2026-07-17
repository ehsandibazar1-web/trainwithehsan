<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک ردیف به‌ازای هر تلاشِ «Generate Hero Image» — هم‌شکل با ai_generations (status
    // queued/processing/completed/failed/cancelled، content_type/content_id با همان morph map
    // کوتاه) اما جدا نگه داشته شده چون خروجی‌اش باینری/Media است، نه متن/آرایه‌ای که
    // ContentAssistantService::parseResponse() می‌فهمد — نگاه کنید به App\Jobs\GenerateHeroImage.
    public function up(): void
    {
        Schema::create('ai_image_generations', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 50);
            $table->unsignedBigInteger('content_id');
            $table->string('prompt_field', 50)->default('hero_image_prompt');
            $table->text('prompt_used')->nullable();
            $table->string('status', 20)->default('queued');
            $table->string('provider_slug', 50)->nullable();
            $table->string('model')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('error')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_image_generations');
    }
};
