<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// مدتِ زمانِ ویدیوی خودمیزبان (ثانیه) — هنگامِ آپلود از هدرِ فایل خوانده و ذخیره می‌شود تا در
// VideoObject (duration) و Video Sitemap (<video:duration>) استفاده شود، بدونِ کوئری/پردازشِ اضافه
// در هر درخواست. برای ویدیوی غیرِویدیویی یا فرمتی که خوانده نشود null می‌ماند (اختیاری، نه الزامی).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('duration_seconds')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
