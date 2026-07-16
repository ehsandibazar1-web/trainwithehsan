<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// همان مشکلی که با ai_generations/taggables رخ داد (نگاه کنید به 2026_07_16_000016)، اینجا هم
// برای notifications پیش آمد: ستون notifiable_type با طول پیش‌فرض VARCHAR(255) (از morphs())
// ساخته شد — خود CREATE TABLE روی production موفق بود اما ALTER TABLE ADD INDEX روی آن با
// «Specified key was too long» شکست خورد و migration ناتمام ماند، یعنی جدول ممکن است از قبل با
// ستون بلند و بدون این ایندکس وجود داشته باشد. این migration ایمن است چه آن حالت نیمه‌کاره وجود
// داشته باشد چه نصب تازه‌ای که 000030 (با طول ۱۰۰ اصلاح‌شده) از ابتدا کامل اجرا شده باشد.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if (Schema::hasIndex('notifications', 'notifications_notifiable_type_notifiable_id_index')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->string('notifiable_type', 100)->change();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        // برگشت‌ناپذیر عمداً — این فقط یک اصلاح طول ستون/ایندکس است، برگرداندن به VARCHAR(255)
        // دوباره همان باگ اصلی را بازمی‌گرداند
    }
};
