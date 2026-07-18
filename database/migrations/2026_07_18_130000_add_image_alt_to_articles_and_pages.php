<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ALTِ عکسِ شاخص (هیرو) — روی خودِ مقاله/صفحه، نه روی ردیفِ Media. چون EN و TR دو رکوردِ جدان،
// هر زبان ALTِ خودش را می‌گیرد (همان مدلِ دو-ردیف-به-ازای-هر-ترجمه). nullable: خالی که باشد،
// تمپلیت به عنوانِ مقاله fallback می‌کند، پس رفتارِ فعلی برای رکوردهای موجود دست‌نخورده می‌ماند.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image_path');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->string('image_alt')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('image_alt');
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('image_alt');
        });
    }
};
