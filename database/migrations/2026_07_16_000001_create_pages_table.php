<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // صفحات مستقل سایت (Privacy, Terms, FAQ, ...) — کاملاً جدا از مقالات بلاگ
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 5)->default('en'); // 'en' یا 'tr'
            $table->unsignedBigInteger('translation_of')->nullable(); // آی‌دی نسخه‌ی هم‌زبان دیگه (لینک EN↔TR)
            $table->string('title');
            $table->string('slug')->index();
            $table->longText('body');
            $table->string('image_path')->nullable();
            $table->string('status')->default('draft'); // draft | scheduled | published
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['slug', 'locale']);
            $table->foreign('translation_of')->references('id')->on('pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
