<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // پیوست‌های PDF/سند یک ورودی دانش — عمداً از خط‌لوله‌ی App\Services\Media\MediaProcessor عبور
    // نمی‌کنند (آن خط‌لوله فقط برای تصویر است: WebP/thumbnail/responsive)؛ اینجا فقط ذخیره‌ی ساده‌ی
    // فایل روی دیسک است، بدون پردازش تصویر.
    public function up(): void
    {
        Schema::create('knowledge_entry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_entry_id')->constrained()->cascadeOnDelete();
            $table->string('disk_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_entry_attachments');
    }
};
