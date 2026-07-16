<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            // اطلاعات بازگردانی: چه زمانی و توسط چه کسی مقاله‌ی ایمپورت‌شده حذف شد
            $table->timestamp('rolled_back_at')->nullable()->after('image_count');
            $table->foreignId('rolled_back_by')->nullable()->after('rolled_back_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rolled_back_by');
            $table->dropColumn('rolled_back_at');
        });
    }
};
