<?php

namespace App\Services\AiAssistant\Contracts;

/**
 * قرارداد جداگانه از AiProvider — فقط ارائه‌دهنده‌هایی که واقعاً یک API عمومیِ embedding دارند
 * (امروز: OpenAI و Gemini — Anthropic/Grok/DeepSeek ندارند) این را هم پیاده‌سازی می‌کنند، علاوه بر
 * AiProvider خودشان. App\Services\AiAssistant\ProviderManager::embed() تنها مصرف‌کننده است.
 */
interface EmbeddingProvider
{
    /**
     * @param  string[]  $texts
     * @return array<int, float[]> یک بردار به‌ازای هر متن ورودی، به همان ترتیب
     */
    public function embed(array $texts): array;

    /** @return ?array{prompt_tokens: ?int, completion_tokens: ?int} */
    public function lastEmbeddingUsage(): ?array;
}
