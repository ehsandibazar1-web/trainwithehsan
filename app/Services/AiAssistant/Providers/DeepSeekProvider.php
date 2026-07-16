<?php

namespace App\Services\AiAssistant\Providers;

// DeepSeek — همان توضیح GrokProvider: یک سطح API سازگار با OpenAI Chat Completions
class DeepSeekProvider extends OpenAiCompatibleProvider
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.deepseek.com/v1';
    }

    protected function defaultModel(): string
    {
        return 'deepseek-chat';
    }

    protected function missingKeyMessage(): string
    {
        return 'DeepSeek API key is not configured — set it on the DeepSeek row in AI Studio → Provider Settings.';
    }
}
