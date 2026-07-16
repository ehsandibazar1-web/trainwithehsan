<?php

namespace App\Services\AiAssistant\Contracts;

/**
 * پیاده‌سازی اختیاری برای ارائه‌دهنده‌هایی که شمار توکن مصرفی آخرین respond() را می‌دانند —
 * جدا از AiProvider نگه داشته شده تا آن اینترفیس (که تنها قرارداد لازم برای همه‌ی
 * ارائه‌دهنده‌هاست) دست‌نخورده بماند؛ App\Services\AiAssistant\ProviderManager با
 * instanceof چک می‌کند و اگر پیاده‌سازی نشده باشد، فقط توکن را null در لاگ ثبت می‌کند.
 */
interface UsageAwareProvider
{
    /** @return array{prompt_tokens: ?int, completion_tokens: ?int}|null */
    public function lastUsage(): ?array;
}
