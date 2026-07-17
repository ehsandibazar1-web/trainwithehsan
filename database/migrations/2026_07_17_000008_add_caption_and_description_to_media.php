<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // caption/description مثل alt_text ستون‌های ساده روی خودِ Media هستند، نه Article/Page — همان
    // قرارداد قبلی («تصویر متعلق به کتابخانه‌ی رسانه است، نه به مقاله») که alt_text از قبل استفاده
    // می‌کند؛ App\Services\AiAssistant\ActionRegistry::MEDIA_BACKED_FIELDS این سه را یکجا مدیریت
    // می‌کند — نگاه کنید به CLAUDE.md، بخش AI Image Pipeline
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->text('caption')->nullable()->after('alt_text');
            $table->text('description')->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['caption', 'description']);
        });
    }
};
