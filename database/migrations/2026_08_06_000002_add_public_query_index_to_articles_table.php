<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // کوئریِ هسته‌ایِ هر صفحه‌ی عمومی — Article::published()->locale($l)->orderByDesc('published_at') —
    // هیچ ایندکسِ پشتیبانی نداشت (فقط slug ایندکس داشت)، پس روی locale/status فیلتر می‌کرد و
    // published_at را filesort می‌کرد. یک ایندکسِ ترکیبی دقیقاً به همین ترتیب اضافه می‌شود.
    //
    // status هیچ سقفِ طولِ صریحی در create_articles_table ندارد (varchar(255) پیش‌فرضِ Laravel) —
    // اندیس‌کردنِ کاملِ آن در یک ایندکسِ ترکیبی روی هاستِ production واقعاً با خطای MySQL 1071
    // («Specified key was too long; max key length is 1000 bytes») شکست خورد. مقدارهای واقعیِ
    // status (draft/scheduled/published) کوتاه‌اند، پس فقط یک پیشوندِ ۲۰کاراکتری اندیس می‌شود —
    // کاملاً کافی و همیشه امن، صرف‌نظر از charset/انجینِ دقیقِ جدولِ production. SQLite (لوکال/تست)
    // نحوِ «column(length)» را نمی‌شناسد و اصلاً چنین محدودیتی هم ندارد، پس فقط روی MySQL از این
    // شکلِ raw استفاده می‌شود.
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('alter table `articles` add index `articles_locale_status_published_at_index` (`locale`, `status`(20), `published_at`)');

            return;
        }

        Schema::table('articles', function (Blueprint $table) {
            $table->index(['locale', 'status', 'published_at'], 'articles_locale_status_published_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex('articles_locale_status_published_at_index');
        });
    }
};
