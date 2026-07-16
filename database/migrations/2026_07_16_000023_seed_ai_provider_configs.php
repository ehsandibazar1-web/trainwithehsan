<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // پنج ارائه‌دهنده‌ی خواسته‌شده را از قبل می‌سازد (غیرفعال، بدون کلید) تا صفحه‌ی Provider
    // Settings از همان اول این پنج ردیف را نشان دهد، نه یک فرم خالیِ «ایجاد جدید» — افزودن
    // ارائه‌دهنده‌ی ششم بعداً فقط یک کلاس PHP تازه + یک ردیف دیگر در این جدول (دستی یا با
    // migration مشابه) می‌خواهد، نه تغییر ساختار
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        DB::table('ai_provider_configs')->insert([
            ['slug' => 'anthropic', 'name' => 'Anthropic Claude', 'default_model' => 'claude-sonnet-4-5', 'is_enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'openai', 'name' => 'OpenAI', 'default_model' => 'gpt-5', 'is_enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'gemini', 'name' => 'Google Gemini', 'default_model' => 'gemini-2.5-pro', 'is_enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'grok', 'name' => 'xAI Grok', 'default_model' => 'grok-4', 'is_enabled' => false, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'deepseek', 'name' => 'DeepSeek', 'default_model' => 'deepseek-chat', 'is_enabled' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $anthropicId = DB::table('ai_provider_configs')->where('slug', 'anthropic')->value('id');

        DB::table('ai_provider_settings')->insert([
            'default_provider_config_id' => $anthropicId,
            'failover_enabled' => false,
            'fallback_provider_config_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('ai_provider_settings')->delete();
        DB::table('ai_provider_configs')->whereIn('slug', ['anthropic', 'openai', 'gemini', 'grok', 'deepseek'])->delete();
    }
};
