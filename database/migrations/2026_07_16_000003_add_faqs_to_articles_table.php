<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            // پرسش‌های متداول هر مقاله (اختیاری) — آرایه‌ی JSON از {question, answer}
            // به‌ازای هر ردیف مقاله (هر زبان جدا) ذخیره می‌شود، پس چندزبانگی طبق مدل دو-ردیفه حفظ می‌شود
            $table->json('faqs')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn('faqs');
        });
    }
};
