<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\EmbeddingProvider;
use App\Services\AiAssistant\Contracts\ImageProvider;
use Illuminate\Support\Facades\Http;

class OpenAiProvider extends OpenAiCompatibleProvider implements EmbeddingProvider, ImageProvider
{
    private ?array $lastEmbeddingUsage = null;

    private ?array $lastImageUsage = null;

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

    // POST /v1/images/generations — شکل رسمی OpenAI برای تولید تصویر (gpt-image-1/dall-e-3/
    // dall-e-2)، جدا از chat/completions که respond() استفاده می‌کند. عمداً پارامتر
    // response_format فرستاده نمی‌شود (gpt-image-1 اصلاً این پارامتر را قبول نمی‌کند و همیشه
    // b64_json برمی‌گرداند؛ dall-e-3/2 پیش‌فرضشان هم url است) — به‌جایش هر دو شکلِ ممکنِ پاسخ
    // (b64_json مستقیم، یا url که باید واکشی شود) اینجا مدیریت می‌شوند تا این متد با هر مدلی کار کند.
    public function generateImage(string $prompt, array $options = []): array
    {
        $key = $this->credentials->apiKey;

        if (blank($key)) {
            throw new \RuntimeException($this->missingKeyMessage());
        }

        if (blank($this->credentials->model)) {
            throw new \RuntimeException('No image model is configured for OpenAI — set one on the OpenAI row in AI Studio → Provider Settings.');
        }

        $endpoint = rtrim($this->credentials->baseUrl ?: $this->defaultBaseUrl(), '/').'/images/generations';

        $response = Http::withToken($key)
            ->timeout($this->credentials->timeoutSeconds ?? 120)
            ->connectTimeout(10)
            ->post($endpoint, array_filter([
                'model' => $this->credentials->model,
                'prompt' => $prompt,
                'n' => 1,
                'size' => $options['size'] ?? '1024x1024',
            ]));

        if (! $response->successful()) {
            throw new \RuntimeException("OpenAI image generation request failed: {$response->status()} {$response->body()}");
        }

        $item = $response->json('data.0');

        if (! $item) {
            throw new \RuntimeException('OpenAI image generation returned no image.');
        }

        if (! empty($item['b64_json'])) {
            $bytes = base64_decode($item['b64_json'], true);
        } elseif (! empty($item['url'])) {
            $fetched = Http::timeout(60)->get($item['url']);

            if (! $fetched->successful()) {
                throw new \RuntimeException('Could not download the generated image from OpenAI.');
            }

            $bytes = $fetched->body();
        } else {
            throw new \RuntimeException('OpenAI image generation response had neither b64_json nor url.');
        }

        $usage = $response->json('usage');
        $this->lastImageUsage = $usage ? [
            'prompt_tokens' => $usage['input_tokens'] ?? null,
            'completion_tokens' => $usage['output_tokens'] ?? null,
        ] : null;

        return [
            'bytes' => $bytes,
            'mime_type' => 'image/png',
            'revised_prompt' => $item['revised_prompt'] ?? null,
        ];
    }

    public function lastImageUsage(): ?array
    {
        return $this->lastImageUsage;
    }
}
