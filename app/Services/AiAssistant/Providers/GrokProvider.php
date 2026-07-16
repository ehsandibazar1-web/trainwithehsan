<?php

namespace App\Services\AiAssistant\Providers;

// xAI Grok — رسماً یک سطح API سازگار با OpenAI Chat Completions ارائه می‌کند (فقط base URL و
// نام مدل فرق دارد)، برای همین از همان پایه‌ی مشترک استفاده می‌کند، نه پیاده‌سازی جداگانه
class GrokProvider extends OpenAiCompatibleProvider
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.x.ai/v1';
    }

    protected function defaultModel(): string
    {
        return 'grok-4';
    }

    protected function missingKeyMessage(): string
    {
        return 'xAI Grok API key is not configured — set it on the Grok row in AI Studio → Provider Settings.';
    }
}
