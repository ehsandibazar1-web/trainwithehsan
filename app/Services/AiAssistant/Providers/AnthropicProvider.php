<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Contracts\UsageAwareProvider;
use App\Services\AiAssistant\Support\ProviderCredentials;
use Illuminate\Support\Facades\Http;

/**
 * دو حالت ساخت دارد، عمداً: بدون آرگومان (یا credentials=null) دقیقاً همان رفتار قبل از
 * Provider Manager را دارد — کلید/مدل را از config('services.anthropic.*') می‌خواند، همان
 * چیزی که App\Providers\AppServiceProvider::register() برای مسیر پشتیبان (.env) هنوز
 * می‌سازد و تست‌های موجود روی آن تکیه می‌کنند. وقتی App\Services\AiAssistant\ProviderManager
 * یک ProviderCredentials (از دیتابیس) بدهد، همان مقادیر جای config() را می‌گیرند. هیچ رفتار
 * قبلی تغییر نکرده — فقط منبع پیکربندی قابل‌تزریق شده است.
 */
class AnthropicProvider implements AiProvider, UsageAwareProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    private ?array $lastUsage = null;

    public function __construct(private readonly ?ProviderCredentials $credentials = null) {}

    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string
    {
        $key = $this->credentials?->apiKey ?? config('services.anthropic.key');

        if (blank($key)) {
            throw new \RuntimeException('Anthropic API key is not configured (services.anthropic.key / ANTHROPIC_API_KEY).');
        }

        $content = [];

        foreach ($images as $imageUrl) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $imageUrl]];
        }

        $content[] = ['type' => 'text', 'text' => $userPrompt];

        $endpoint = $this->credentials?->baseUrl ?: self::ENDPOINT;
        $model = $options['model'] ?? $this->credentials?->model ?? config('services.anthropic.model');
        $maxTokens = $options['max_tokens'] ?? $this->credentials?->maxTokens ?? 2048;
        $temperature = $options['temperature'] ?? $this->credentials?->temperature;
        $timeout = $options['timeout'] ?? $this->credentials?->timeoutSeconds ?? 60;

        $payload = [
            'model' => $model,
            'max_tokens' => $maxTokens,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $content],
            ],
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout($timeout)
            ->connectTimeout(10)
            ->post($endpoint, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Anthropic API request failed: '.$response->status().' '.$response->body());
        }

        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        if (blank($text)) {
            throw new \RuntimeException('Anthropic API returned an empty response.');
        }

        $usage = $response->json('usage');
        $this->lastUsage = $usage ? [
            'prompt_tokens' => $usage['input_tokens'] ?? null,
            'completion_tokens' => $usage['output_tokens'] ?? null,
        ] : null;

        return $text;
    }

    public function lastUsage(): ?array
    {
        return $this->lastUsage;
    }
}
