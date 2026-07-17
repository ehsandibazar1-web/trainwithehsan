<?php

namespace App\Services\AiAssistant;

use App\Models\AiActionOverride;
use App\Models\AiProviderConfig;
use App\Models\AiProviderModel;
use App\Models\AiProviderSetting;
use App\Models\AiUsageLog;
use App\Services\AiAssistant\Contracts\AiProvider;
use App\Services\AiAssistant\Contracts\EmbeddingProvider;
use App\Services\AiAssistant\Contracts\ImageProvider;
use App\Services\AiAssistant\Contracts\UsageAwareProvider;
use App\Services\AiAssistant\Providers\AnthropicProvider;
use App\Services\AiAssistant\Providers\DeepSeekProvider;
use App\Services\AiAssistant\Providers\GeminiProvider;
use App\Services\AiAssistant\Providers\GrokProvider;
use App\Services\AiAssistant\Providers\OpenAiProvider;
use App\Services\AiAssistant\Support\ProviderCredentials;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * تنها نقطه‌ای که App\Services\AiAssistant\ContentAssistantService با آن حرف می‌زند —
 * ContentAssistantService دیگر مستقیماً به یک AiProvider ثابت متصل نیست (که در معماری قبلی
 * یک‌بار در زمان bind شدنِ container انتخاب می‌شد)، بلکه هر تماس با respond() یک ارائه‌دهنده‌ی
 * تازه بر اساس کلید اکشن (action_key از ActionRegistry) انتخاب می‌کند — همان چیزی که
 * override دانه‌ریز per-field را ممکن می‌کند.
 *
 * مسیر پشتیبان: اگر هیچ ارائه‌دهنده‌ای در دیتابیس فعال/دارای کلید نباشد (نصب تازه، هیچ‌کس هنوز
 * صفحه‌ی Provider Settings را لمس نکرده)، دقیقاً همان AiProvider قدیمی (bind شده در
 * AppServiceProvider، خوانده‌شده از config('services.anthropic.*')) استفاده می‌شود — رفتار
 * قبل از این معماری برای هرکسی که فقط ANTHROPIC_API_KEY را در .env گذاشته، صددرصد حفظ می‌ماند.
 */
class ProviderManager
{
    // افزودن ارائه‌دهنده‌ی تازه = یک ردیف اینجا + یک کلاس Provider تازه، هیچ‌جای دیگری تغییر نمی‌کند
    private const DRIVERS = [
        'anthropic' => AnthropicProvider::class,
        'openai' => OpenAiProvider::class,
        'gemini' => GeminiProvider::class,
        'grok' => GrokProvider::class,
        'deepseek' => DeepSeekProvider::class,
    ];

    public function __construct(private readonly AiProvider $legacyProvider) {}

    /** @return array<string, class-string<AiProvider>> */
    public static function availableDrivers(): array
    {
        return self::DRIVERS;
    }

    /**
     * @param  string[]  $images
     * @param  array<string, mixed>  $options
     */
    public function respond(
        string $systemPrompt,
        string $userPrompt,
        array $images = [],
        array $options = [],
        ?string $actionKey = null,
        ?string $contentType = null,
        ?int $contentId = null,
    ): string {
        $candidates = $this->resolveCandidates($actionKey);
        $lastException = null;

        foreach ($candidates as $candidate) {
            [$text, $exception] = $this->attempt($candidate, $systemPrompt, $userPrompt, $images, $options, $actionKey, $contentType, $contentId);

            if ($text !== null) {
                return $text;
            }

            $lastException = $exception;
        }

        throw new \RuntimeException(
            'All configured AI providers failed. '.($lastException?->getMessage() ?? 'Unknown error.'),
            previous: $lastException
        );
    }

    /**
     * یک ارائه‌دهنده را تا ۲ بار امتحان می‌کند (۱ تلاش مجدد با تأخیر کوتاه، برای خطاهای گذرا)،
     * نتیجه‌ی نهایی (موفق یا ناموفق) را یک‌بار در ai_usage_logs ثبت می‌کند.
     *
     * @return array{0: ?string, 1: ?Throwable}
     */
    private function attempt(array $candidate, string $systemPrompt, string $userPrompt, array $images, array $options, ?string $actionKey, ?string $contentType, ?int $contentId): array
    {
        $exception = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $start = microtime(true);

            try {
                $text = $candidate['provider']->respond($systemPrompt, $userPrompt, $images, $options);
                $elapsedMs = (int) round((microtime(true) - $start) * 1000);
                $usage = $candidate['provider'] instanceof UsageAwareProvider ? $candidate['provider']->lastUsage() : null;

                $this->logUsage($candidate, $actionKey, $contentType, $contentId, $elapsedMs, $usage, 'success', null);

                return [$text, null];
            } catch (Throwable $e) {
                $exception = $e;

                if ($attempt < 2) {
                    usleep(300_000);
                }
            }
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        $this->logUsage($candidate, $actionKey, $contentType, $contentId, $elapsedMs, null, 'failed', $this->sanitizeError($exception));

        return [null, $exception];
    }

    /**
     * فهرست مرتب ارائه‌دهنده‌های قابل‌تلاش برای این اکشن — اول override دانه‌ریز (اگر ست و
     * قابل‌استفاده باشد)، وگرنه پیش‌فرض سراسری، وگرنه مسیر پشتیبان .env؛ اگر failover روشن باشد
     * و مسیر اصلی از دیتابیس آمده باشد، ارائه‌دهنده‌ی fallback هم به‌عنوان تلاش دوم اضافه می‌شود.
     *
     * @return array<int, array{provider: AiProvider, provider_slug: string, model: ?string}>
     */
    private function resolveCandidates(?string $actionKey): array
    {
        $settings = AiProviderSetting::current();
        $primaryConfig = null;
        $primaryModel = null;

        if ($actionKey) {
            $override = AiActionOverride::with('providerConfig')->where('action_key', $actionKey)->first();

            if ($override && $override->providerConfig?->is_usable) {
                $primaryConfig = $override->providerConfig;
                $primaryModel = $override->model;
            }
        }

        if (! $primaryConfig && $settings->defaultProvider?->is_usable) {
            $primaryConfig = $settings->defaultProvider;
        }

        if (! $primaryConfig) {
            return [[
                'provider' => $this->legacyProvider,
                'provider_slug' => 'anthropic',
                'model' => config('services.anthropic.model'),
            ]];
        }

        $candidates = [[
            'provider' => $this->buildProvider($primaryConfig, $primaryModel),
            'provider_slug' => $primaryConfig->slug,
            'model' => $primaryModel ?: $primaryConfig->default_model,
        ]];

        if ($settings->failover_enabled
            && $settings->fallbackProvider?->is_usable
            && $settings->fallback_provider_config_id !== $primaryConfig->id) {
            $candidates[] = [
                'provider' => $this->buildProvider($settings->fallbackProvider),
                'provider_slug' => $settings->fallbackProvider->slug,
                'model' => $settings->fallbackProvider->default_model,
            ];
        }

        return $candidates;
    }

    /**
     * ارائه‌دهنده را مستقیماً از این یک ردیف config می‌سازد (بدون عبور از resolveCandidates —
     * یعنی وضعیت is_enabled/is_default/override هیچ‌کدام دخیل نیستند)، یک تماس آزمایشی کوتاه
     * می‌زند و نتیجه (وضعیت/تأخیر/مدل/خطای پاک‌سازی‌شده) را هم برمی‌گرداند و هم روی همان ردیف
     * ai_provider_configs ذخیره می‌کند. عمداً چیزی در ai_usage_logs ثبت نمی‌کند — این یک تماس
     * تولید محتوای واقعی نیست.
     *
     * @return array{status: string, latency_ms: ?int, model: ?string, error: ?string}
     */
    public function testConnection(AiProviderConfig $config): array
    {
        if (! filled($config->api_key)) {
            return ['status' => 'failed', 'latency_ms' => null, 'model' => $config->default_model, 'error' => 'No API key is set for this provider yet.'];
        }

        if (! isset(self::DRIVERS[$config->slug])) {
            return ['status' => 'failed', 'latency_ms' => null, 'model' => $config->default_model, 'error' => "No provider implementation is registered for \"{$config->slug}\"."];
        }

        $provider = $this->buildProvider($config);
        $start = microtime(true);

        try {
            $provider->respond('You are a connection test.', 'Reply with exactly one word: OK', [], ['max_tokens' => 10]);
            $result = ['status' => 'success', 'latency_ms' => (int) round((microtime(true) - $start) * 1000), 'model' => $config->default_model, 'error' => null];
        } catch (Throwable $e) {
            $result = ['status' => 'failed', 'latency_ms' => (int) round((microtime(true) - $start) * 1000), 'model' => $config->default_model, 'error' => $this->sanitizeError($e)];
        }

        $config->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => $result['status'],
            'last_test_latency_ms' => $result['latency_ms'],
            'last_test_model' => $result['model'],
            'last_test_error' => $result['error'],
        ])->save();

        return $result;
    }

    /**
     * ارائه‌دهنده‌ی فعلاً پیکربندی‌شده برای embedding — یا null اگر چیزی تنظیم نشده یا آنچه تنظیم
     * شده دیگر قابل‌استفاده نیست (کلید حذف شده، غیرفعال شده، مدل embedding خالی است). هیچ مسیر
     * پشتیبان .env ای برای embedding وجود ندارد (بر خلاف respond()) چون هیچ نصب قدیمی‌ای هرگز
     * از این قابلیت استفاده نمی‌کرده — این یک قابلیت کاملاً تازه است.
     */
    public function resolveEmbeddingProvider(): ?AiProviderConfig
    {
        $config = AiProviderSetting::current()->embeddingProvider;

        return $config?->is_usable_for_embeddings ? $config : null;
    }

    /**
     * @param  string[]  $texts
     * @return array<int, float[]>
     *
     * @throws \RuntimeException اگر هیچ ارائه‌دهنده‌ی embedding پیکربندی نشده باشد یا تماس شکست بخورد
     *                           — بر خلاف respond()، اینجا هیچ failover/retry ای نیست: بازیابیِ
     *                           دانش همیشه یک fallback کلمه‌ای دارد (KnowledgeBaseService) که این
     *                           throw را می‌گیرد، پس پیچیدگیِ retry اینجا لازم نیست.
     */
    public function embed(array $texts, ?string $contentType = null, ?int $contentId = null): array
    {
        $config = $this->resolveEmbeddingProvider();

        if (! $config) {
            throw new \RuntimeException('No embedding provider is configured — set one in AI Studio → AI Routing → Embeddings.');
        }

        $provider = $this->buildEmbeddingProvider($config);
        $start = microtime(true);

        try {
            $vectors = $provider->embed($texts);
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            $this->logEmbeddingUsage($config, $contentType, $contentId, count($texts), $elapsedMs, $provider->lastEmbeddingUsage(), 'success', null);

            return $vectors;
        } catch (Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);
            $this->logEmbeddingUsage($config, $contentType, $contentId, count($texts), $elapsedMs, null, 'failed', $this->sanitizeError($e));

            throw $e;
        }
    }

    private function buildEmbeddingProvider(AiProviderConfig $config): EmbeddingProvider
    {
        $class = self::DRIVERS[$config->slug] ?? null;

        if (! $class || ! is_subclass_of($class, EmbeddingProvider::class)) {
            throw new \RuntimeException("Provider \"{$config->slug}\" does not support embeddings.");
        }

        $credentials = new ProviderCredentials(
            apiKey: $config->api_key,
            baseUrl: $config->base_url,
            model: $config->embedding_model,
            timeoutSeconds: $config->timeout_seconds,
        );

        return new $class($credentials);
    }

    private function logEmbeddingUsage(AiProviderConfig $config, ?string $contentType, ?int $contentId, int $inputCount, int $elapsedMs, ?array $usage, string $status, ?string $error): void
    {
        try {
            $promptTokens = $usage['prompt_tokens'] ?? null;

            AiUsageLog::create([
                'provider_slug' => $config->slug,
                'model' => $config->embedding_model,
                'action_key' => 'embedding',
                'content_type' => $contentType,
                'content_id' => $contentId,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => null,
                'total_tokens' => $promptTokens,
                'estimated_cost_usd' => $this->estimateCost($config->slug, $config->embedding_model, $promptTokens, 0),
                'response_time_ms' => $elapsedMs,
                'status' => $status,
                'error_message' => $error,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * ارائه‌دهنده‌ی فعلاً پیکربندی‌شده‌ی پیش‌فرض برای تولید تصویر — یا null. هم‌روحِ
     * resolveEmbeddingProvider()، برای این‌که UI (مثلا دکمه‌ی «Generate Hero Image») بتواند بدون
     * صدازدنِ متد خصوصیِ resolveImageCandidates() چک کند که اصلاً چیزی تنظیم شده یا نه.
     */
    public function resolveImageProvider(): ?AiProviderConfig
    {
        $config = AiProviderSetting::current()->defaultImageProvider;

        return $config?->is_usable_for_image_generation ? $config : null;
    }

    /**
     * تولید یک تصویر — بر خلاف embed() (که عمداً failover ندارد چون بازیابیِ دانش خودش یک
     * fallback کلمه‌ای دارد)، اینجا واقعاً باید failover کند: هیچ مسیر جایگزینی برای «این تصویر
     * تولید نشد» وجود ندارد، پس دقیقاً همان الگوی چندکاندیدِ respond() تکرار می‌شود (پیش‌فرض، سپس
     * fallback در صورت فعال‌بودن image_failover_enabled) — طبق درخواستِ صریح کاربر: «یک معماریِ
     * کاملاً مشابهِ ارائه‌دهنده‌ی متنی». هیچ مسیر پشتیبان .env ای وجود ندارد (تولید تصویر یک
     * قابلیت کاملاً تازه است، هیچ نصب قدیمی‌ای برایش نگه‌داشتنیِ سازگاری لازم ندارد).
     *
     * @param  array<string, mixed>  $options
     * @return array{bytes: string, mime_type: string, revised_prompt: ?string, provider_slug: string}
     *
     * @throws \RuntimeException اگر هیچ ارائه‌دهنده‌ی تولید تصویری پیکربندی نشده باشد یا همه شکست بخورند
     */
    public function generateImage(string $prompt, array $options = [], ?string $contentType = null, ?int $contentId = null): array
    {
        $candidates = $this->resolveImageCandidates();

        if ($candidates === []) {
            throw new \RuntimeException('No image-generation provider is configured — set one in AI Studio → AI Routing → Image Generation.');
        }

        $lastException = null;

        foreach ($candidates as $candidate) {
            [$result, $exception] = $this->attemptImage($candidate, $prompt, $options, $contentType, $contentId);

            if ($result !== null) {
                return array_merge($result, ['provider_slug' => $candidate['provider_slug'], 'model' => $candidate['model']]);
            }

            $lastException = $exception;
        }

        throw new \RuntimeException(
            'All configured image-generation providers failed. '.($lastException?->getMessage() ?? 'Unknown error.'),
            previous: $lastException
        );
    }

    /**
     * @return array{0: ?array, 1: ?Throwable}
     */
    private function attemptImage(array $candidate, string $prompt, array $options, ?string $contentType, ?int $contentId): array
    {
        $exception = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $start = microtime(true);

            try {
                $result = $candidate['provider']->generateImage($prompt, $options);
                $elapsedMs = (int) round((microtime(true) - $start) * 1000);
                $usage = $candidate['provider']->lastImageUsage();

                $this->logImageUsage($candidate, $contentType, $contentId, $elapsedMs, $usage, 'success', null);

                return [$result, null];
            } catch (Throwable $e) {
                $exception = $e;

                if ($attempt < 2) {
                    usleep(300_000);
                }
            }
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);
        $this->logImageUsage($candidate, $contentType, $contentId, $elapsedMs, null, 'failed', $this->sanitizeError($exception));

        return [null, $exception];
    }

    /** @return array<int, array{provider: ImageProvider, provider_slug: string, model: ?string}> */
    private function resolveImageCandidates(): array
    {
        $settings = AiProviderSetting::current();
        $primaryConfig = $this->resolveImageProvider();

        if (! $primaryConfig) {
            return [];
        }

        $candidates = [[
            'provider' => $this->buildImageProvider($primaryConfig),
            'provider_slug' => $primaryConfig->slug,
            'model' => $primaryConfig->image_model,
        ]];

        if ($settings->image_failover_enabled
            && $settings->fallbackImageProvider?->is_usable_for_image_generation
            && $settings->fallback_image_provider_config_id !== $primaryConfig->id) {
            $candidates[] = [
                'provider' => $this->buildImageProvider($settings->fallbackImageProvider),
                'provider_slug' => $settings->fallbackImageProvider->slug,
                'model' => $settings->fallbackImageProvider->image_model,
            ];
        }

        return $candidates;
    }

    private function buildImageProvider(AiProviderConfig $config): ImageProvider
    {
        $class = self::DRIVERS[$config->slug] ?? null;

        if (! $class || ! is_subclass_of($class, ImageProvider::class)) {
            throw new \RuntimeException("Provider \"{$config->slug}\" does not support image generation.");
        }

        $credentials = new ProviderCredentials(
            apiKey: $config->api_key,
            baseUrl: $config->base_url,
            model: $config->image_model,
            timeoutSeconds: $config->timeout_seconds,
        );

        return new $class($credentials);
    }

    // هزینه‌ی تولید تصویر امروز هرگز تخمین زده نمی‌شود — کاتالوگ AiProviderModel بر اساس قیمتِ
    // هر میلیون توکن است (متنی)، نه قیمتِ ثابتِ هر تصویر؛ null صادقانه به‌جای یک عدد حدسی، هم‌روحِ
    // logEmbeddingUsage/estimateCost بالا
    private function logImageUsage(array $candidate, ?string $contentType, ?int $contentId, int $elapsedMs, ?array $usage, string $status, ?string $error): void
    {
        try {
            AiUsageLog::create([
                'provider_slug' => $candidate['provider_slug'],
                'model' => $candidate['model'],
                'action_key' => 'image_generation',
                'content_type' => $contentType,
                'content_id' => $contentId,
                'prompt_tokens' => $usage['prompt_tokens'] ?? null,
                'completion_tokens' => $usage['completion_tokens'] ?? null,
                'total_tokens' => null,
                'estimated_cost_usd' => null,
                'response_time_ms' => $elapsedMs,
                'status' => $status,
                'error_message' => $error,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function buildProvider(AiProviderConfig $config, ?string $modelOverride = null): AiProvider
    {
        $class = self::DRIVERS[$config->slug] ?? null;

        if (! $class) {
            throw new \RuntimeException("No provider implementation is registered for \"{$config->slug}\".");
        }

        $credentials = new ProviderCredentials(
            apiKey: $config->api_key,
            baseUrl: $config->base_url,
            model: $modelOverride ?: $config->default_model,
            maxTokens: $config->max_tokens,
            temperature: $config->temperature !== null ? (float) $config->temperature : null,
            timeoutSeconds: $config->timeout_seconds,
        );

        return new $class($credentials);
    }

    // این متد عمداً هرگز چیزی throw نمی‌کند — یک خطای دیتابیس هنگام ثبت لاگ مصرف نباید یک
    // فراخوانی هوش مصنوعیِ واقعاً موفق را به‌عنوان شکست به بالادست برگرداند (متن تولیدشده در آن
    // صورت برای همیشه گم می‌شد، فقط به‌خاطر یک مشکل جانبی در لاگ‌گیری)
    private function logUsage(array $candidate, ?string $actionKey, ?string $contentType, ?int $contentId, int $elapsedMs, ?array $usage, string $status, ?string $error): void
    {
        try {
            $promptTokens = $usage['prompt_tokens'] ?? null;
            $completionTokens = $usage['completion_tokens'] ?? null;

            AiUsageLog::create([
                'provider_slug' => $candidate['provider_slug'],
                'model' => $candidate['model'],
                'action_key' => $actionKey,
                'content_type' => $contentType,
                'content_id' => $contentId,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'total_tokens' => ($promptTokens !== null && $completionTokens !== null) ? $promptTokens + $completionTokens : null,
                'estimated_cost_usd' => $this->estimateCost($candidate['provider_slug'], $candidate['model'], $promptTokens, $completionTokens),
                'response_time_ms' => $elapsedMs,
                'status' => $status,
                'error_message' => $error,
                'user_id' => Auth::id(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    // فقط وقتی قیمتِ هر میلیون توکن برای این مدل در کاتالوگ ادمین ثبت شده باشد محاسبه می‌شود —
    // در غیر این صورت null (یک عدد حدسی بهتر از هیچ‌عددی نیست)
    private function estimateCost(string $providerSlug, ?string $model, ?int $promptTokens, ?int $completionTokens): ?float
    {
        if (! $model || $promptTokens === null || $completionTokens === null) {
            return null;
        }

        $providerModel = AiProviderModel::whereHas('providerConfig', fn ($q) => $q->where('slug', $providerSlug))
            ->where('model', $model)
            ->first();

        if (! $providerModel || $providerModel->input_price_per_million === null || $providerModel->output_price_per_million === null) {
            return null;
        }

        return round(
            ($promptTokens / 1_000_000) * (float) $providerModel->input_price_per_million
            + ($completionTokens / 1_000_000) * (float) $providerModel->output_price_per_million,
            6
        );
    }

    // پیام خطا قبل از ذخیره در ai_usage_logs پاک‌سازی می‌شود — کلید API هرگز نباید در متن خطا
    // ذخیره شود، حتی اگر یک ارائه‌دهنده به‌اشتباه آن را در پاسخ/URL بازتاب دهد
    private function sanitizeError(?Throwable $e): ?string
    {
        if (! $e) {
            return null;
        }

        $message = $e->getMessage();
        $message = preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message);
        $message = preg_replace('/(Bearer\s+)\S+/i', '$1[redacted]', $message);
        $message = preg_replace('/(x-api-key["\']?\s*[:=]\s*["\']?)[^"\'\s,}]+/i', '$1[redacted]', $message);

        return mb_substr($message, 0, 2000);
    }
}
