<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک یافته‌ی AI Agent — نتیجه‌ی App\Services\AiAgent\AgentAuditService، چرخه‌ی
    // pending/applied/rejected دارد (هم‌روح approved/dismissed در internal_link_suggestions):
    // بازتولید هرگز ردیف‌های applied/rejected را دست نمی‌زند، فقط pending را به‌روز/حذف می‌کند.
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_run_id')->nullable()->constrained('ai_audit_runs')->nullOnDelete();

            // ستون‌های کلیدِ یکتایی عمداً NULL نیستند (پیش‌فرض '' / ۰) — MySQL/SQLite هر NULL را
            // در unique index «متفاوت از هر NULL دیگر» می‌بینند، پس اگر این‌ها NULL می‌بودند
            // upsert() هرگز یافته‌های بدون content_id مشخص (مثلا «Blog index») را در اجراهای بعدی
            // به‌روز نمی‌کرد و هر بار یک ردیف تکراری تازه می‌ساخت
            $table->string('category'); // content_refresh | missing_internal_links | missing_faq | ...
            $table->string('content_type')->default(''); // Article | Page | Media | Blog index | ...
            $table->unsignedBigInteger('content_id')->default(0);
            $table->string('related_content_type')->default(''); // برای یافته‌های جفتی (تاپیک تکراری، کانیبالیزیشن)
            $table->unsignedBigInteger('related_content_id')->default(0);
            $table->string('locale', 5)->default('');

            $table->string('severity')->default('notice'); // notice | warning
            $table->string('title');
            $table->text('detail');
            $table->string('edit_url')->nullable();
            $table->string('related_edit_url')->nullable();

            // مسیر رفعِ یک‌کلیکی — null یعنی فقط-گزارشی (نیاز به قضاوت ادمین دارد، رفع خودکار ندارد)
            $table->string('fix_type')->nullable(); // field | internal_links | translate
            $table->string('fix_field')->nullable(); // کلید App\Services\AiAssistant\ActionRegistry
            $table->string('fix_mode')->nullable();
            $table->foreignId('ai_generation_id')->nullable()->constrained('ai_generations')->nullOnDelete();

            $table->string('status')->default('pending'); // pending | applied | rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // نام صریح و کوتاه — طبق درسِ ai_generation_knowledge_entry (SQLSTATE 42000/1059)،
            // نام خودکار Laravel برای این تعداد ستون از سقف ۶۴ کاراکتریِ MySQL عبور می‌کند
            $table->unique(
                ['category', 'content_type', 'content_id', 'related_content_type', 'related_content_id', 'locale'],
                'ai_recommendation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
