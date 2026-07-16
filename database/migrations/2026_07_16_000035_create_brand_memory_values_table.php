<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // مقدار یک بخش حافظه‌ی برند به یک زبان مشخص — نه یک زبان کامل سایت (که فقط en/tr است)، بلکه
    // زبانی که این متن مرجع/دانش برند به آن نوشته شده؛ fa (فارسی) هم اینجا مجاز است چون این صرفاً
    // محتوای مرجع برای پرامپت‌هاست، نه یک لوکیل واقعی صفحات عمومی سایت (نگاه کنید به CLAUDE.md).
    public function up(): void
    {
        Schema::create('brand_memory_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_memory_section_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->longText('content')->nullable();
            $table->timestamps();

            $table->unique(['brand_memory_section_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_memory_values');
    }
};
