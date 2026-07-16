<?php

namespace App\Services\AiAssistant\Providers;

class OpenAiProvider extends OpenAiCompatibleProvider
{
    protected function defaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    protected function defaultModel(): string
    {
        return 'gpt-5';
    }

    protected function missingKeyMessage(): string
    {
        return 'OpenAI API key is not configured — set it on the OpenAI row in AI Studio → Provider Settings.';
    }
}
