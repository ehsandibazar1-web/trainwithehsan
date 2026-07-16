<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قالب‌های آماده‌ی محتوا — اسکلت JSON/Markdown که در صفحه‌ی AI Import لود می‌شوند
        Schema::create('ai_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('format')->default('json'); // json | markdown
            $table->longText('content');
            $table->timestamps();
        });

        // کتابخانه‌ی پرامپت — متن‌های آماده برای دادن به هوش مصنوعی
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->longText('prompt');
            $table->timestamps();
        });

        // پروفایل‌های هوش مصنوعی — نام ارائه‌دهنده + پیش‌فرض‌هایی که جاهای خالی ایمپورت را پر می‌کنند
        Schema::create('ai_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // claude | chatgpt | ...
            $table->string('default_language', 5)->nullable();
            $table->string('default_status')->nullable();
            $table->string('default_category')->nullable();
            $table->string('default_author')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_profiles');
        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_templates');
    }
};
