<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // یک پیوست حالا می‌تواند یک فایل آپلودشده باشد یا یک صفحه‌ی وبِ واکشی‌شده (source_type='url'،
    // disk_path رشته‌ی خالی می‌ماند نه NULL — تغییر nullable ستون موجود نیازمند doctrine/dbal است
    // که این پروژه عمداً ندارد، پس همان قرارداد «خالی یعنی غایب» که جاهای دیگر این پروژه هم دارد
    // اینجا هم استفاده می‌شود؛ source_url پر می‌شود) — App\Services\Rag\TextExtractionService متن
    // خام را یک‌بار استخراج و در extracted_text کش می‌کند تا نیازی به استخراج مجدد در هر
    // ایندکس‌سازی نباشد؛ extraction_status/extraction_error برای نمایش وضعیت در UI Knowledge Base.
    public function up(): void
    {
        Schema::table('knowledge_entry_attachments', function (Blueprint $table) {
            $table->string('source_type', 10)->default('file')->after('knowledge_entry_id'); // file | url
            $table->string('source_url')->nullable()->after('source_type');
            $table->string('extraction_status', 20)->default('pending')->after('size'); // pending | extracted | failed
            $table->longText('extracted_text')->nullable()->after('extraction_status');
            $table->timestamp('extracted_at')->nullable()->after('extracted_text');
            $table->text('extraction_error')->nullable()->after('extracted_at');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_entry_attachments', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_url', 'extraction_status', 'extracted_text', 'extracted_at', 'extraction_error']);
        });
    }
};
