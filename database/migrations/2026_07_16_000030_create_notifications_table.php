<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // شکل استاندارد جدول اعلان‌های لاراول (php artisan notifications:table) — دستی نوشته شده
    // چون این پروژه migration را تولید نکرده بود؛ همان ستون‌هایی که Illuminate\Notifications
    // و پنل Filament (databaseNotifications()) به‌صورت پیش‌فرض انتظار دارند.
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            // به‌جای morphs() پیش‌فرض (VARCHAR(255)): همان درسِ ai_generations/taggables — این دو
            // ستون بخشی از ایندکس ترکیبی زیرند و VARCHAR(255) با utf8mb4 روی MySQL از سقف طول
            // کلید ایندکس رد می‌شود («Specified key was too long» — خطای واقعی روی production)
            $table->string('notifiable_type', 100);
            $table->unsignedBigInteger('notifiable_id');
            $table->index(['notifiable_type', 'notifiable_id']);
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
