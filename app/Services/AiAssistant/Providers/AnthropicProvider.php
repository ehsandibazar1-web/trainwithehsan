<?php

namespace App\Services\AiAssistant\Providers;

use App\Services\AiAssistant\Contracts\AiProvider;
use Illuminate\Support\Facades\Http;

class AnthropicProvider implements AiProvider
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const API_VERSION = '2023-06-01';

    public function respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string
    {
        $key = config('services.anthropic.key');

        if (blank($key)) {
            throw new \RuntimeException('Anthropic API key is not configured (services.anthropic.key / ANTHROPIC_API_KEY).');
        }

        $content = [];

        foreach ($images as $imageUrl) {
            $content[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $imageUrl]];
        }

        $content[] = ['type' => 'text', 'text' => $userPrompt];

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout($options['timeout'] ?? 60)
            ->connectTimeout(10)
            ->post(self::ENDPOINT, [
                'model' => $options['model'] ?? config('services.anthropic.model'),
                'max_tokens' => $options['max_tokens'] ?? 2048,
                'system' => $systemPrompt,
                'messages' => [
                    ['role' => 'user', 'content' => $content],
                ],
            ]);

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

        return $text;
    }
}
