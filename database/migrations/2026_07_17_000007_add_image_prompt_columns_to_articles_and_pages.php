<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // چهار متنِ prompt جداگانه برای هیرو/تامبنیل/OG/سوشال — ذخیره می‌شوند حتی وقتی فقط prompt
    // هیرو امروز واقعاً به تولید تصویر می‌رسد (طبق تصمیم تأییدشده‌ی کاربر)، تا نسخه‌های بعدی
    // بتوانند تولید تصویرِ جداگانه برای تامبنیل/OG/سوشال را بدون تغییر schema اضافه کنند — نگاه
    // کنید به CLAUDE.md، بخش AI Image Pipeline
    public function up(): void
    {
        foreach (['articles', 'pages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('hero_image_prompt')->nullable()->after('image_path');
                $table->text('thumbnail_image_prompt')->nullable()->after('hero_image_prompt');
                $table->text('og_image_prompt')->nullable()->after('thumbnail_image_prompt');
                $table->text('social_image_prompt')->nullable()->after('og_image_prompt');
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'pages'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['hero_image_prompt', 'thumbnail_image_prompt', 'og_image_prompt', 'social_image_prompt']);
            });
        }
    }
};
