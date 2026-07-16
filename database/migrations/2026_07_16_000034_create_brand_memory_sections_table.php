<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک تکه از حافظه‌ی برند (مثلاً «لحن نوشتار» یا «کلمات ممنوعه») — قابل‌تنظیم عمداً: ادمین
    // می‌تواند بخش سفارشی تازه اضافه کند (is_system=false) یا هر بخشی را موقتاً از پرامپت
    // خارج کند (is_enabled=false) بدون حذف محتوایش. بخش‌های پیش‌فرض (is_system=true) با یک
    // migration seed می‌شوند و قابل‌حذف نیستند — فقط قابل‌غیرفعال‌سازی، همان روحیه‌ی
    // WorkflowStage اما با یک قفل صریح به‌جای گارد «آیا استفاده شده».
    public function up(): void
    {
        Schema::create('brand_memory_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label');
            $table->string('group', 100);
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_memory_sections');
    }
};
