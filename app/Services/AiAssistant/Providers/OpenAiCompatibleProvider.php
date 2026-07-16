<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Contracts\UsageAwareProvider;
use App\Services\AiAssistant\Support\ProviderCredentials;
use Illuminate\Support\Facades\Http;

/**
 * پایه‌ی مشترک برای هر ارائه‌دهنده‌ای که همان شکل درخواست/پاسخ «Chat Completions» را دنبال
 * می‌کند (OpenAI خودش، و همچنین xAI Grok و DeepSeek — هر دو رسماً یک سطح API سازگار با
 * OpenAI دارند). فقط base URL و مدل پیش‌فرض بین زیرکلاس‌ها فرق می‌کند؛ منطق ساخت درخواست،
 * پارس پاسخ، و استخراج usage یکی است — اینجا سه‌بار تکرار نمی‌شود.
 */
abstract class OpenAiCompatibleProvider implements AiProvider, UsageAwareProvider
{
    private ?array $lastUsage = null;

    public function __construct(protected readonly ProviderCredentials $credentials) {}

    abstract protected function defaultBaseUrl(): string;

    abstract protected function defaultModel(): string;

    abstract protected function missingKeyMessage(): string;

    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException($this->missingKeyMessage());
        }

        $content = [['type' => 'text', 'text' => $userPrompt]];

        foreach ($images as $imageUrl) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $imageUrl]];
        }

        $endpoint = rtrim($this->credentials->baseUrl ?: $this->defaultBaseUrl(), '/').'/chat/completions';
        $model = $options['model'] ?? $this->credentials->model ?? $this->defaultModel();
        $maxTokens = $options['max_tokens'] ?? $this->credentials->maxTokens ?? 2048;
        $temperature = $options['temperature'] ?? $this->credentials->temperature;
        $timeout = $options['timeout'] ?? $this->credentials->timeoutSeconds ?? 60;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $response = Http::withToken($key)
            ->timeout($timeout)
            ->connectTimeout(10)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException(static::class." request failed: {$response->status()} {$response->body()}");
        }

        $text = (string) $response->json('choices.0.message.content');

        if (blank($text)) {
            throw new \RuntimeException(static::class.' returned an empty response.');
        }

        $usage = $response->json('usage');
        $this->lastUsage = $usage ? [
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => $usage['completion_tokens'] ?? null,
        ] : null;

        return $text;
    }

    public function lastUsage(): ?array
    {
        return $this->lastUsage;
    }
}
