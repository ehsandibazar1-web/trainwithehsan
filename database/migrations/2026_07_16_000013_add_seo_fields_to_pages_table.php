<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            // همان فیلدهای سئو/OG که به articles اضافه شد — Page اصلاً excerpt ندارد،
            // پس توضیح صفحه قبلاً همیشه از body مشتق می‌شد؛ این فیلدها override اختیاری‌اند
            $table->string('seo_title')->nullable()->after('body');
            $table->string('meta_description')->nullable()->after('seo_title');
            $table->string('og_title')->nullable()->after('meta_description');
            $table->string('og_description')->nullable()->after('og_title');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['seo_title', 'meta_description', 'og_title', 'og_description']);
        });
    }
};
