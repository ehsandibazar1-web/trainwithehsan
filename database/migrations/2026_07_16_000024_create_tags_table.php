<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // برچسب‌گذاری محتوا برای سازمان‌دهی/فیلتر — عمداً جدایی از Keyword (که فقط برای تحلیل و
        // بهینه‌سازی سئو است، نه سازمان‌دهی محتوا). نگاه کنید به CLAUDE.md بخش برنامه‌ریز محتوا.
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
