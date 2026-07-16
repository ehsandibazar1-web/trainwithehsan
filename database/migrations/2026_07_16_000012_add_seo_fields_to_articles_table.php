<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // فیلدهای سئو/OG اختصاصی — قبلاً وجود نداشتند؛ توضیح صفحه از excerpt/body مشتق می‌شد.
            // خالی یعنی همان رفتار قبلی (مشتق‌شده در Blade) حفظ می‌شود، این فیلدها override اختیاری‌اند
            $table->string('seo_title')->nullable()->after('excerpt');
            $table->string('meta_description')->nullable()->after('seo_title');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->string('og_description')->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'meta_description', 'og_title', 'og_description']);
        });
    }
};
