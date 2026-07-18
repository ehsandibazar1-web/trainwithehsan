<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // nullable و بدون مقدار پیش‌فرض — یعنی توکن‌های موجود («هرگز منقضی نمی‌شوند») دقیقاً
        // همان رفتار قبلی را حفظ می‌کنند؛ انقضا صرفاً یک قابلیت اختیاریِ جدید است
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
