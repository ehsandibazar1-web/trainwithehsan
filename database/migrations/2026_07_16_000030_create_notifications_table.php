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
            $table->morphs('notifiable');
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
