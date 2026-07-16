<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// این migration برای همان مشکلی است که 2026_07_16_000014 روی production واقعی رخ داد:
// ستون‌های content_type/field با طول پیش‌فرض VARCHAR(255) ساخته شدند (خود CREATE TABLE موفق
// بود)، اما ALTER TABLE ADD INDEX روی آن‌ها با خطای «Specified key was too long» (MySQL، سقف
// طول کلید با utf8mb4) شکست خورد و migration ناتمام ماند — یعنی جدول روی MySQL production ممکن
// است از قبل با ستون‌های بلند و بدون این ایندکس وجود داشته باشد. این migration ایمن است چه آن
// حالت نیمه‌کاره وجود داشته باشد چه نصب تازه‌ای که 000014 (با طول ۵۰ اصلاح‌شده) کامل اجرا شده —
// اگر جدول نباشد کاری نمی‌کند، و drop+recreate این دو ستون هیچ داده‌ی واقعی از دست نمی‌دهد
// (این جدول تاریخچه است، فقط چند دقیقه پیش و نیمه‌کاره ساخته شده بود).
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_generations')) {
            return;
        }

        // SQLite حذف ستونی که بخشی از یک ایندکس است را رد می‌کند مگر اول خود ایندکس حذف شود —
        // روی production که ADD INDEX از همان اول شکست خورده بود این ایندکس اصلاً وجود ندارد،
        // پس چک hasIndex لازم است (dropIndex روی ایندکسِ ناموجود در SQLite/MySQL هم خطا می‌دهد)
        if (Schema::hasIndex('ai_generations', 'ai_generations_content_type_content_id_field_index')) {
            Schema::table('ai_generations', function (Blueprint $table) {
                $table->dropIndex('ai_generations_content_type_content_id_field_index');
            });
        }

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->dropColumn(['content_type', 'field']);
        });

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->string('content_type', 50)->after('user_id');
            $table->string('field', 50)->after('content_id');
        });

        Schema::table('ai_generations', function (Blueprint $table) {
            $table->index(['content_type', 'content_id', 'field']);
        });
    }

    public function down(): void
    {
        // برگشت‌ناپذیر عمداً — این فقط یک اصلاح طول ستون است، برگرداندن به VARCHAR(255) دوباره
        // همان باگ اصلی را بازمی‌گرداند
    }
};
