<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مشترکین خبرنامه — دابل آپت‌این: تا روی لینک تأیید کلیک نکنند فعال نمی‌شوند
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('status')->default('subscribed'); // subscribed | unsubscribed
            $table->string('verification_token', 64)->index();
            $table->timestamp('verification_sent_at')->nullable(); // مبنای انقضای ۲۴ساعته لینک تأیید
            $table->string('unsubscribe_token', 64)->index();
            $table->string('locale', 5)->default('en'); // en | tr
            $table->string('source')->default('footer');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('verified_at')->nullable(); // null = هنوز تأیید نشده
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
