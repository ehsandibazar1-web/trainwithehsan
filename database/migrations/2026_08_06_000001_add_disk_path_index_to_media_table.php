<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // media.disk_path هیچ ایندکسی نداشت با اینکه کلیدِ join/lookupِ اصلیِ هر تصویرِ عمومی است
    // (Media::forRecord/optimizedUrl/srcsetFor همه یک where('disk_path', ...) می‌زنند) — بدونِ
    // ایندکس هرکدام یک full-scanِ جدولِ media است. این مهاجرت فقط ایندکس اضافه می‌کند، هیچ داده‌ای
    // تغییر نمی‌کند.
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->index('disk_path');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['disk_path']);
        });
    }
};
