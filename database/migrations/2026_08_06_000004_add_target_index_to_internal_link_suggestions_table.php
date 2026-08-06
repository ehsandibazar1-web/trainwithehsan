<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ایندکسِ ترکیبیِ موجود (source_type, source_id, target_type, target_id) فقط از سمتِ
    // source قابلِ‌استفاده‌ی کامل است (پیشوندِ leftmost) — کوئری‌ای که فقط بر اساسِ target فیلتر
    // می‌کند (مثلاً «همه‌ی پیشنهادهایی که به این مقاله هدف‌گذاری شده‌اند») از آن ایندکس استفاده
    // نمی‌کند. یک ایندکسِ جداگانه برایِ سمتِ target اضافه می‌شود.
    //
    // target_type هم مثلِ articles.status هیچ سقفِ طولِ صریحی ندارد (varchar(255) پیش‌فرض) —
    // همان محافظتِ پیشوندِ ۲۰کاراکتری روی MySQL که مهاجرتِ articles/pages گرفت، اینجا هم پیشگیرانه
    // اعمال می‌شود (مقدارهای واقعی 'Article'/'Page' کوتاه‌اند) تا همان خطای ۱۰۷۱ رخ ندهد.
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('alter table `internal_link_suggestions` add index `internal_link_suggestions_target_index` (`target_type`(20), `target_id`)');

            return;
        }

        Schema::table('internal_link_suggestions', function (Blueprint $table) {
            $table->index(['target_type', 'target_id'], 'internal_link_suggestions_target_index');
        });
    }

    public function down(): void
    {
        Schema::table('internal_link_suggestions', function (Blueprint $table) {
            $table->dropIndex('internal_link_suggestions_target_index');
        });
    }
};
