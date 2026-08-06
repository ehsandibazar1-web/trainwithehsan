<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // کوئریِ هسته‌ایِ هر صفحه‌ی عمومی — Article::published()->locale($l)->orderByDesc('published_at') —
    // هیچ ایندکسِ پشتیبانی نداشت (فقط slug ایندکس داشت)، پس روی locale/status فیلتر می‌کرد و
    // published_at را filesort می‌کرد. یک ایندکسِ ترکیبی دقیقاً به همین ترتیب اضافه می‌شود.
    public function up(): void
    {
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
