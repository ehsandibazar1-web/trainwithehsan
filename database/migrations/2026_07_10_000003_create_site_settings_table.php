<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();   // مثلاً: home.hero.slide.1
            $table->longText('value')->nullable(); // متن ساده یا JSON
            $table->string('group')->nullable();   // برای دسته‌بندی توی پنل مدیریت
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
