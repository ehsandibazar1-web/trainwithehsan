<?php

namespace Tests\Feature;

use App\Models\AiActionOverride;
use App\Models\AiProviderConfig;
use App\Models\AiProviderModel;
use App\Models\AiProviderSetting;
use App\Models\AiUsageLog;
use App\Services\AiAssistant\ProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProviderManagerTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $slug, array $overrides = []): AiProviderConfig
    {
        $config = AiProviderConfig::where('slug', $slug)->firstOrFail();
        $config->fill(array_merge(['is_enabled' => true, 'api_key' => 'test-key-for-'.$slug], $overrides));
        $config->save();

        return $config;
    }

    // ============ Legacy .env fallback (no DB provider configured) ============

    public function test_falls_back_to_legacy_null_provider_when_nothing_is_configured_anywhere(): void
    {
        config(['services.anthropic.key' => null]);

        $this->expectException(\RuntimeException::class);

        try {
            app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');
        } finally {
            $log = AiUsageLog::sole();
            $this->assertSame('failed', $log->status);
            $this->assertSame('seo_title', $log->action_key);
        }
    }

    public function test_falls_back_to_legacy_env_config_when_no_db_provider_is_usable(): void
    {
        config(['services.anthropic.key' => 'legacy-env-key', 'services.anthropic.model' => 'claude-sonnet-4-5']);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Legacy env result']],
            'usage' => ['input_tokens' => 11, 'output_tokens' => 4],
        ], 200)]);

        $text = app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertSame('Legacy env result', $text);

        $log = AiUsageLog::sole();
        $this->assertSame('anthropic', $log->provider_slug);
        $this->assertSame('success', $log->status);
        $this->assertSame(11, $log->prompt_tokens);
        $this->assertSame(4, $log->completion_tokens);
        $this->assertSame(15, $log->total_tokens);
    }

    // ============ DB-configured default provider ============

    public function test_db_configured_default_provider_takes_priority_over_legacy_env(): void
    {
        config(['services.anthropic.key' => 'legacy-env-key']);

        $openai = $this->enable('openai', ['default_model' => 'gpt-5']);
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OpenAI default result']]],
            'usage' => ['prompt_tokens' => 20, 'completion_tokens' => 9],
        ], 200)]);

        $text = app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertSame('OpenAI default result', $text);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic'));

        $log = AiUsageLog::sole();
        $this->assertSame('openai', $log->provider_slug);
        $this->assertSame('gpt-5', $log->model);
    }

    // ============ Per-action override ============

    public function test_action_override_wins_over_the_global_default_for_that_action_only(): void
    {
        $openai = $this->enable('openai');
        $anthropic = $this->enable('anthropic', ['default_model' => 'claude-sonnet-4-5']);
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);
        AiActionOverride::create(['action_key' => 'translate', 'ai_provider_config_id' => $anthropic->id]);

        Http::fake([
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Anthropic for translate']], 'usage' => ['input_tokens' => 3, 'output_tokens' => 2]], 200),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OpenAI for seo_title']]], 'usage' => ['prompt_tokens' => 3, 'completion_tokens' => 2]], 200),
        ]);

        $translateText = app(ProviderManager::class)->respond('sys', 'user', [], [], 'translate');
        $seoText = app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertSame('Anthropic for translate', $translateText);
        $this->assertSame('OpenAI for seo_title', $seoText);

        $this->assertSame(['anthropic', 'openai'], AiUsageLog::orderBy('id')->pluck('provider_slug')->all());
    }

    public function test_action_override_pointing_at_an_unusable_provider_falls_back_to_the_default(): void
    {
        $openai = $this->enable('openai');
        // gemini اینجا override شده ولی is_enabled=false مانده — یعنی «قابل‌استفاده» نیست
        $gemini = AiProviderConfig::where('slug', 'gemini')->first();
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);
        AiActionOverride::create(['action_key' => 'seo_title', 'ai_provider_config_id' => $gemini->id]);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'Fell back to default']]], 'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1]], 200)]);

        $text = app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertSame('Fell back to default', $text);
        $this->assertSame('openai', AiUsageLog::sole()->provider_slug);
    }

    // ============ Failover ============

    public function test_failover_tries_the_fallback_provider_when_the_default_fails_and_failover_is_enabled(): void
    {
        $openai = $this->enable('openai');
        $anthropic = $this->enable('anthropic');
        AiProviderSetting::current()->update([
            'default_provider_config_id' => $openai->id,
            'failover_enabled' => true,
            'fallback_provider_config_id' => $anthropic->id,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response('Server error', 500),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Rescued by failover']], 'usage' => ['input_tokens' => 2, 'output_tokens' => 1]], 200),
        ]);

        $text = app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertSame('Rescued by failover', $text);

        $logs = AiUsageLog::orderBy('id')->get();
        $this->assertSame(['openai', 'anthropic'], $logs->pluck('provider_slug')->all());
        $this->assertSame(['failed', 'success'], $logs->pluck('status')->all());
    }

    public function test_failover_disabled_lets_the_primary_failure_propagate(): void
    {
        $openai = $this->enable('openai');
        $anthropic = $this->enable('anthropic');
        AiProviderSetting::current()->update([
            'default_provider_config_id' => $openai->id,
            'failover_enabled' => false,
            'fallback_provider_config_id' => $anthropic->id,
        ]);

        Http::fake(['api.openai.com/*' => Http::response('Server error', 500)]);

        $this->expectException(\RuntimeException::class);

        try {
            app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');
        } finally {
            // فقط openai امتحان شده — anthropic که failover غیرفعال است هرگز صدا زده نمی‌شود
            $this->assertSame(['openai'], AiUsageLog::pluck('provider_slug')->all());
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'anthropic'));
        }
    }

    // ============ Cost estimation ============

    public function test_estimated_cost_is_computed_when_the_model_catalog_has_pricing(): void
    {
        $openai = $this->enable('openai', ['default_model' => 'gpt-5']);
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);
        AiProviderModel::create([
            'ai_provider_config_id' => $openai->id,
            'label' => 'GPT-5',
            'model' => 'gpt-5',
            'input_price_per_million' => 5.0,
            'output_price_per_million' => 15.0,
        ]);

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Priced result']]],
            'usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 1_000_000],
        ], 200)]);

        app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertEquals(20.0, (float) AiUsageLog::sole()->estimated_cost_usd);
    }

    public function test_estimated_cost_is_null_without_a_matching_priced_model(): void
    {
        $openai = $this->enable('openai', ['default_model' => 'gpt-5']);
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Unpriced result']]],
            'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
        ], 200)]);

        app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');

        $this->assertNull(AiUsageLog::sole()->estimated_cost_usd);
    }

    // ============ Security: never log API keys ============

    public function test_error_messages_never_persist_a_bearer_token_or_query_string_key(): void
    {
        $openai = $this->enable('openai', ['api_key' => 'sk-super-secret-value']);
        AiProviderSetting::current()->update(['default_provider_config_id' => $openai->id]);

        Http::fake(['api.openai.com/*' => Http::response('Unauthorized: Bearer sk-super-secret-value rejected', 401)]);

        try {
            app(ProviderManager::class)->respond('sys', 'user', [], [], 'seo_title');
        } catch (\RuntimeException) {
            // مورد انتظار — فقط لاگ را چک می‌کنیم
        }

        $log = AiUsageLog::sole();
        $this->assertStringNotContainsString('sk-super-secret-value', $log->error_message ?? '');
    }
}
