<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ترجیح هر کاربر برای هر رویداد+کانال — نبودِ ردیف یعنی «فعال» (مدل opt-out، نه opt-in) تا
    // نصب‌های تازه بدون تنظیم دستی هم اعلان دریافت کنند؛ نگاه کنید به
    // App\Models\NotificationPreference::isEnabled()
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 50);
            $table->string('channel', 20);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'event_key', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
