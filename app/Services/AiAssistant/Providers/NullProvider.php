<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\AiProvider;

// وقتی هیچ کلید API ای تنظیم نشده باشد از این استفاده می‌شود — به‌جای تلاش برای یک تماس شبکه‌ای
// محکوم‌به‌شکست، بلافاصله و با پیام روشن خطا می‌دهد
class NullProvider implements AiProvider
{
    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string
    {
        throw new \RuntimeException('No AI provider is configured. Set ANTHROPIC_API_KEY in .env to enable the AI Content Assistant.');
    }
}
