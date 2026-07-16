<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->default('panel'); // panel | api (آینده)
            $table->string('ai_provider')->nullable();
            $table->string('format')->nullable(); // json | markdown
            $table->string('status'); // imported | failed
            $table->json('errors')->nullable();
            $table->json('warnings')->nullable();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('article_title')->nullable();
            $table->string('locale', 5)->nullable();
            $table->unsignedInteger('faq_count')->default(0);
            $table->unsignedInteger('image_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
