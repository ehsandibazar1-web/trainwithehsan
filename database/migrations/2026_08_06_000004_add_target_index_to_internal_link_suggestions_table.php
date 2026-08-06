<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // ایندکسِ ترکیبیِ موجود (source_type, source_id, target_type, target_id) فقط از سمتِ
    // source قابلِ‌استفاده‌ی کامل است (پیشوندِ leftmost) — کوئری‌ای که فقط بر اساسِ target فیلتر
    // می‌کند (مثلاً «همه‌ی پیشنهادهایی که به این مقاله هدف‌گذاری شده‌اند») از آن ایندکس استفاده
    // نمی‌کند. یک ایندکسِ جداگانه برایِ سمتِ target اضافه می‌شود.
    public function up(): void
    {
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
