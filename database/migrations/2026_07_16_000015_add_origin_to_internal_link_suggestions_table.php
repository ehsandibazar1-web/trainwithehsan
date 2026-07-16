<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_link_suggestions', function (Blueprint $table) {
            // rule_based (App\Services\InternalLinking\SuggestionEngine) | ai (App\Filament\Pages\AiContentAssistant)
            // پیشنهادهای rule_based و ai برای همان جفت source/target می‌توانند وجود داشته باشند —
            // محدودیت یکتایی جدول همچنان روی (source,target) است، پس اگر هر دو یک جفت را پیشنهاد
            // بدهند در یک ردیف ادغام می‌شوند؛ این عمداً است، نه یک باگ
            $table->string('origin')->default('rule_based')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('internal_link_suggestions', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
