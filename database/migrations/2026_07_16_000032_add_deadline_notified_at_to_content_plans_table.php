<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // جلوی ارسال تکراری اعلان مهلت را می‌گیرد — بدون این ستون، دستور notify-deadlines
    // (که هر ساعت اجرا می‌شود) هر بار که due_at هنوز داخل بازه‌ی «فردا» باشد دوباره اعلان
    // می‌فرستاد. با تغییر due_at این ستون به null برمی‌گردد (نگاه کنید به ContentPlan::booted)
    public function up(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->timestamp('deadline_notified_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn('deadline_notified_at');
        });
    }
};
