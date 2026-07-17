<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\EmbeddingProvider;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends OpenAiCompatibleProvider implements EmbeddingProvider
{
    private ?array $lastEmbeddingUsage = null;

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

    // POST /v1/embeddings — شکل درخواست/پاسخِ رسمی OpenAI برای embedding، جدا از chat/completions
    // که respond() بالا استفاده می‌کند؛ به همین خاطر اینجا پیاده‌سازیِ مستقل خودش را دارد، نه از
    // طریق منطق مشترکِ OpenAiCompatibleProvider (که فقط برای شکل chat/completions ساخته شده).
    public function embed(array $texts): array
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException($this->missingKeyMessage());
        }

        if (blank($this->credentials->model)) {
            throw new \RuntimeException('No embedding model is configured for OpenAI — set one on the OpenAI row in AI Studio → Provider Settings.');
        }

        $endpoint = rtrim($this->credentials->baseUrl ?: $this->defaultBaseUrl(), '/').'/embeddings';

        $response = Http::withToken($key)
            ->timeout($this->credentials->timeoutSeconds ?? 60)
            ->connectTimeout(10)
            ->post($endpoint, [
                'model' => $this->credentials->model,
                'input' => array_values($texts),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI embeddings request failed: {$response->status()} {$response->body()}");
        }

        $data = collect($response->json('data', []))->sortBy('index')->values();

        if ($data->count() !== count($texts)) {
            throw new \RuntimeException('OpenAI embeddings response did not return one vector per input text.');
        }

        $usage = $response->json('usage');
        $this->lastEmbeddingUsage = $usage ? [
            'prompt_tokens' => $usage['prompt_tokens'] ?? null,
            'completion_tokens' => null,
        ] : null;

        return $data->map(fn (array $item) => $item['embedding'])->all();
    }

    public function lastEmbeddingUsage(): ?array
    {
        return $this->lastEmbeddingUsage;
    }
}
