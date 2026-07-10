<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('en'); // 'en' یا 'tr'
            $table->unsignedBigInteger('translation_of')->nullable(); // آی‌دی نسخه‌ی هم‌زبان دیگه (لینک EN↔TR)
            $table->string('title');
            $table->string('slug')->index();
            $table->string('category')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->string('author_name')->default('Ehsan Dibazar');
            $table->unsignedInteger('reading_time')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->string('status')->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'locale']);
            $table->foreign('translation_of')->references('id')->on('articles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
