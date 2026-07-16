<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // مراحل خط‌تولید محتوا — قابل‌تنظیم عمداً: ادمین می‌تواند از /admin/workflow-stages مرحله‌ی
    // تازه اضافه کند یا برچسب/ترتیب را عوض کند؛ فقط اسلاگ‌های پیش‌فرض (idea/ai_draft/human_review/
    // seo_review/published/archived) در کد به‌عنوان نقاط یکپارچگی شناخته می‌شوند (نگاه کنید به
    // App\Models\ContentPlan::moveToStage() و App\Console\Commands\PublishDueArticles) — حذف/
    // تغییرِ اسلاگِ این ردیف‌های خاص یعنی آن یکپارچگی‌های خاص دیگر فعال نمی‌شوند، نه اینکه کل
    // سیستم می‌شکند.
    public function up(): void
    {
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('color', 20)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_terminal')->default(false);
            // آرایه‌ای از {key,label} — چک‌لیست این مرحله (مثلاً SEO Review: Meta Title, FAQ, ...)؛
            // وضعیتِ تیک‌خورده/نخورده هر آیتم روی content_plans.checklist_state ذخیره می‌شود، نه اینجا
            $table->json('checklist_items')->nullable();
            $table->timestamps();
        });

        $now = now();

        $seoChecklist = json_encode([
            ['key' => 'meta_title', 'label' => 'Meta Title'],
            ['key' => 'meta_description', 'label' => 'Meta Description'],
            ['key' => 'faq', 'label' => 'FAQ'],
            ['key' => 'internal_links', 'label' => 'Internal Links'],
            ['key' => 'images', 'label' => 'Images'],
            ['key' => 'schema', 'label' => 'Schema'],
            ['key' => 'alt_text', 'label' => 'ALT Text'],
        ], JSON_UNESCAPED_UNICODE);

        DB::table('workflow_stages')->insert([
            ['slug' => 'idea', 'label' => 'Idea', 'sort_order' => 1, 'color' => '#9ca3af', 'is_default' => true, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'research', 'label' => 'Research', 'sort_order' => 2, 'color' => '#60a5fa', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ai_draft', 'label' => 'AI Draft', 'sort_order' => 3, 'color' => '#a78bfa', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'human_review', 'label' => 'Human Review', 'sort_order' => 4, 'color' => '#f59e0b', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'seo_review', 'label' => 'SEO Review', 'sort_order' => 5, 'color' => '#f97316', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => $seoChecklist, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'scheduled', 'label' => 'Scheduled', 'sort_order' => 6, 'color' => '#38bdf8', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'published', 'label' => 'Published', 'sort_order' => 7, 'color' => '#22c55e', 'is_default' => false, 'is_terminal' => false, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'archived', 'label' => 'Archived', 'sort_order' => 8, 'color' => '#6b7280', 'is_default' => false, 'is_terminal' => true, 'checklist_items' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stages');
    }
};
