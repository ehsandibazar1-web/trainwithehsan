<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // همان دلیلِ مهاجرتِ articles — Page هم دقیقاً همان الگویِ published()->locale()->orderByDesc('published_at')
    // را در sitemap/کوئری‌های عمومی دارد.
    public function up(): void
    {
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
