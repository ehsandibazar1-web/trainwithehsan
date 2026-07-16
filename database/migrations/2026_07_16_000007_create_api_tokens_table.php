<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // توکن‌های API ایمپورت — فقط هشِ توکن ذخیره می‌شود؛ متن کامل فقط یک‌بار هنگام ساخت نمایش داده می‌شود
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('token_hash', 64)->unique();
            $table->string('prefix', 12); // برای شناسایی توکن در پنل بدون لو رفتن متن کامل
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('import_logs', function (Blueprint $table) {
            $table->foreignId('api_token_id')->nullable()->after('user_id')->constrained('api_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('import_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_token_id');
        });

        Schema::dropIfExists('api_tokens');
    }
};
