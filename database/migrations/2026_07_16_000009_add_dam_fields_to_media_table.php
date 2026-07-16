<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('disk')->default('public')->after('id');
            // پوشه‌ی رسانه — nullable یعنی پوشه‌ی ریشه؛ حذف پوشه فقط رسانه را بی‌پوشه می‌کند، هرگز فایل را حذف نمی‌کند
            $table->foreignId('folder_id')->nullable()->after('disk')->constrained('media_folders')->nullOnDelete();
            $table->string('alt_text')->nullable()->after('original_name');
            $table->unsignedInteger('width')->nullable()->after('size');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('webp_path')->nullable()->after('height');       // نسخه‌ی WebP کامل، هم‌ابعاد اصلی
            $table->string('thumbnail_path')->nullable()->after('webp_path'); // تامبنیل برای شبکه‌ی کتابخانه رسانه
            $table->json('responsive_paths')->nullable()->after('thumbnail_path'); // نگاشت عرض → مسیر، برای srcset
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
            $table->dropColumn(['disk', 'alt_text', 'width', 'height', 'webp_path', 'thumbnail_path', 'responsive_paths']);
        });
    }
};
