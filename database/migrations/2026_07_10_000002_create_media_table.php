<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('disk_path');       // مسیر ذخیره‌شده روی سرور
            $table->string('url');             // آدرس عمومی قابل‌استفاده
            $table->string('type');            // image | video
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable(); // بایت
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
