<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // تکمیل فیلدهای سئوی صفحات مستقل — meta_keywords/canonical_url/robots در کنار seo_title/
    // meta_description/og_title/og_description موجود (مهاجرت ۲۰۲۶_۰۷_۱۶_۰۰۰۰۱۳). faqs عیناً
    // همون ستون/شکل روی articles (JSON، cast به آرایه) است تا صفحاتی مثل FAQ بتوانند از همون
    // اسکیمای FAQPage استفاده کنند — سیستم سئوی دومی ساخته نمی‌شود.
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('meta_keywords')->nullable()->after('og_description');
            $table->string('canonical_url')->nullable()->after('meta_keywords');
            $table->string('robots')->nullable()->after('canonical_url');
            $table->json('faqs')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['meta_keywords', 'canonical_url', 'robots', 'faqs']);
        });
    }
};
