<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // همان دلیلِ مهاجرتِ articles — Page هم دقیقاً همان الگویِ published()->locale()->orderByDesc('published_at')
    // را در sitemap/کوئری‌های عمومی دارد. همان محافظتِ پیشوندِ status(20) روی MySQL، به همان
    // دلیل (خطای ۱۰۷۱ MySQL روی production، نگاه کنید به مهاجرتِ articles).
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('alter table `pages` add index `pages_locale_status_published_at_index` (`locale`, `status`(20), `published_at`)');

            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->index(['locale', 'status', 'published_at'], 'pages_locale_status_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_locale_status_published_at_index');
        });
    }
};
