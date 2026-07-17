<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Contracts\EmbeddingProvider;
use App\Services\AiAssistant\Contracts\ImageProvider;
use App\Services\AiAssistant\Contracts\UsageAwareProvider;
use App\Services\AiAssistant\Support\ProviderCredentials;
use Illuminate\Support\Facades\Http;

/**
 * Google Gemini (Generative Language API) — شکل درخواست/پاسخش با بقیه فرق دارد (system_instruction
 * جدا از contents، کلید API به‌جای هدر در query string، تصویرها به‌صورت inline_data باید ارسال
 * شوند نه یک URL عمومی)، برای همین پایه‌ی OpenAiCompatibleProvider را به اشتراک نمی‌گذارد.
 */
class GeminiProvider implements AiProvider, EmbeddingProvider, ImageProvider, UsageAwareProvider
{
    private const DEFAULT_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private ?array $lastUsage = null;

    private ?array $lastEmbeddingUsage = null;

    private ?array $lastImageUsage = null;

    public function __construct(private readonly ProviderCredentials $credentials) {}

    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException('Google Gemini API key is not configured — set it on the Gemini row in AI Studio → Provider Settings.');
        }

        $parts = [['text' => $userPrompt]];

        foreach ($images as $imageUrl) {
            if ($inline = $this->fetchImageAsInlineData($imageUrl)) {
                $parts[] = ['inline_data' => $inline];
            }
        }

        $baseUrl = rtrim($this->credentials->baseUrl ?: self::DEFAULT_BASE_URL, '/');
        $model = $options['model'] ?? $this->credentials->model ?? 'gemini-2.5-pro';
        $maxTokens = $options['max_tokens'] ?? $this->credentials->maxTokens ?? 2048;
        $temperature = $options['temperature'] ?? $this->credentials->temperature;
        $timeout = $options['timeout'] ?? $this->credentials->timeoutSeconds ?? 60;

        $generationConfig = ['maxOutputTokens' => $maxTokens];
        if ($temperature !== null) {
            $generationConfig['temperature'] = $temperature;
        }

        // کلید API در Gemini به‌جای هدر Authorization در query string فرستاده می‌شود — طبق مستندات
        // رسمی همین API، نه یک انتخاب دلخواه اینجا
        $response = Http::timeout($timeout)
            ->connectTimeout(10)
            ->post("{$baseUrl}/models/{$model}:generateContent?key={$key}", [
                'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => [['role' => 'user', 'parts' => $parts]],
                'generationConfig' => $generationConfig,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini API request failed: '.$response->status().' '.$response->body());
        }

        $text = (string) $response->json('candidates.0.content.parts.0.text');

        if (blank($text)) {
            throw new \RuntimeException('Gemini API returned an empty response.');
        }

        $usage = $response->json('usageMetadata');
        $this->lastUsage = $usage ? [
            'prompt_tokens' => $usage['promptTokenCount'] ?? null,
            'completion_tokens' => $usage['candidatesTokenCount'] ?? null,
        ] : null;

        return $text;
    }

    public function lastUsage(): ?array
    {
        return $this->lastUsage;
    }

    // POST /models/{model}:batchEmbedContents — Gemini چند متن را در یک تماس embed می‌کند
    // (برخلاف OpenAI که یک آرایه‌ی «input» ساده می‌گیرد)؛ usageMetadata رسمی برای این endpoint
    // مستند نیست، پس هزینه هرگز حدس زده نمی‌شود — null صادقانه، هم‌روح ProviderManager::estimateCost
    public function embed(array $texts): array
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException('Google Gemini API key is not configured — set it on the Gemini row in AI Studio → Provider Settings.');
        }

        $model = $this->credentials->model;

        if (blank($model)) {
            throw new \RuntimeException('No embedding model is configured for Gemini — set one on the Gemini row in AI Studio → Provider Settings.');
        }

        $baseUrl = rtrim($this->credentials->baseUrl ?: self::DEFAULT_BASE_URL, '/');
        $timeout = $this->credentials->timeoutSeconds ?? 60;

        $requests = array_map(fn (string $text) => [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $text]]],
        ], array_values($texts));

        $response = Http::timeout($timeout)
            ->connectTimeout(10)
            ->post("{$baseUrl}/models/{$model}:batchEmbedContents?key={$key}", ['requests' => $requests]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini embeddings request failed: '.$response->status().' '.$response->body());
        }

        $embeddings = collect($response->json('embeddings', []));

        if ($embeddings->count() !== count($texts)) {
            throw new \RuntimeException('Gemini embeddings response did not return one vector per input text.');
        }

        $this->lastEmbeddingUsage = null;

        return $embeddings->map(fn (array $item) => $item['values'])->all();
    }

    public function lastEmbeddingUsage(): ?array
    {
        return $this->lastEmbeddingUsage;
    }

    // POST /models/{model}:predict — Imagen روی همین Generative Language API، شکلِ رسمیِ
    // درخواست/پاسخِ Imagen (نه generateContent که respond() بالا استفاده می‌کند): instances/parameters
    // به‌جای contents، و تصویر به‌صورت base64 مستقیم در predictions برمی‌گردد — هیچ URL ای برای
    // واکشی وجود ندارد، برخلاف OpenAI.
    public function generateImage(string $prompt, array $options = []): array
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException('Google Gemini API key is not configured — set it on the Gemini row in AI Studio → Provider Settings.');
        }

        $model = $this->credentials->model;

        if (blank($model)) {
            throw new \RuntimeException('No image model is configured for Gemini — set one on the Gemini row in AI Studio → Provider Settings.');
        }

        $baseUrl = rtrim($this->credentials->baseUrl ?: self::DEFAULT_BASE_URL, '/');
        $timeout = $this->credentials->timeoutSeconds ?? 120;

        $response = Http::timeout($timeout)
            ->connectTimeout(10)
            ->post("{$baseUrl}/models/{$model}:predict?key={$key}", [
                'instances' => [['prompt' => $prompt]],
                'parameters' => ['sampleCount' => 1],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Gemini image generation request failed: '.$response->status().' '.$response->body());
        }

        $prediction = $response->json('predictions.0');
        $base64 = $prediction['bytesBase64Encoded'] ?? null;

        if (! $base64) {
            throw new \RuntimeException('Gemini image generation returned no image.');
        }

        $bytes = base64_decode($base64, true);

        if ($bytes === false) {
            throw new \RuntimeException('Gemini image generation returned an unreadable image.');
        }

        $this->lastImageUsage = null;

        return [
            'bytes' => $bytes,
            'mime_type' => $prediction['mimeType'] ?? 'image/png',
            'revised_prompt' => null,
        ];
    }

    public function lastImageUsage(): ?array
    {
        return $this->lastImageUsage;
    }

    /** @return array{mime_type: string, data: string}|null */
    private function fetchImageAsInlineData(string $imageUrl): ?array
    {
        try {
            $response = Http::timeout(15)->get($imageUrl);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        return [
            'mime_type' => $response->header('Content-Type') ?: 'image/jpeg',
            'data' => base64_encode($response->body()),
        ];
    }
}
