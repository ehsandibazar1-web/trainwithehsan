<?php

namespace Tests\Feature;

use App\Filament\Pages\AiActionRouting;
use App\Filament\Resources\AiProviderConfigs\Pages\EditAiProviderConfig;
use App\Filament\Resources\AiProviderConfigs\Pages\ListAiProviderConfigs;
use App\Models\AiActionOverride;
use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_ai_providers_resource_lists_the_five_seeded_providers(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/ai-provider-configs')
            ->assertOk()
            ->assertSee('Anthropic Claude')
            ->assertSee('OpenAI')
            ->assertSee('Google Gemini')
            ->assertSee('xAI Grok')
            ->assertSee('DeepSeek');
    }

    public function test_editing_a_provider_saves_an_encrypted_key_and_never_redisplays_it(): void
    {
        $config = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(EditAiProviderConfig::class, ['record' => $config->id])
            ->fillForm([
                'name' => 'OpenAI',
                'api_key' => 'sk-brand-new-secret',
                'default_model' => 'gpt-5',
                'is_enabled' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $config->refresh();
        $this->assertSame('sk-brand-new-secret', $config->api_key);
        $this->assertTrue($config->is_enabled);

        // فرم را دوباره باز می‌کنیم — کلید هرگز نباید به‌صورت خام در state فرم پر شود
        Livewire::actingAs($owner)
            ->test(EditAiProviderConfig::class, ['record' => $config->id])
            ->assertFormSet(['api_key' => null]);
    }

    public function test_leaving_the_api_key_blank_on_save_keeps_the_existing_key(): void
    {
        $config = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $config->update(['api_key' => 'sk-original-key']);

        Livewire::actingAs($this->owner())
            ->test(EditAiProviderConfig::class, ['record' => $config->id])
            ->fillForm(['name' => 'OpenAI (renamed)'])
            ->call('save')
            ->assertHasNoFormErrors();

        $config->refresh();
        $this->assertSame('sk-original-key', $config->api_key);
        $this->assertSame('OpenAI (renamed)', $config->name);
    }

    public function test_test_connection_action_records_success_and_latency_on_the_config_row(): void
    {
        $config = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $config->update(['api_key' => 'sk-test', 'default_model' => 'gpt-5', 'is_enabled' => true]);

        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1],
        ], 200)]);

        Livewire::actingAs($this->owner())
            ->test(ListAiProviderConfigs::class)
            ->callTableAction('testConnection', $config);

        $config->refresh();
        $this->assertSame('success', $config->last_test_status);
        $this->assertNotNull($config->last_tested_at);
        $this->assertNotNull($config->last_test_latency_ms);
    }

    public function test_test_connection_action_records_failure_without_throwing(): void
    {
        $config = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $config->update(['api_key' => 'sk-test', 'default_model' => 'gpt-5', 'is_enabled' => true]);

        Http::fake(['api.openai.com/*' => Http::response('Server error', 500)]);

        Livewire::actingAs($this->owner())
            ->test(ListAiProviderConfigs::class)
            ->callTableAction('testConnection', $config);

        $config->refresh();
        $this->assertSame('failed', $config->last_test_status);
        $this->assertNotNull($config->last_test_error);
    }

    public function test_set_default_action_updates_the_singleton_settings_row(): void
    {
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();

        Livewire::actingAs($this->owner())
            ->test(ListAiProviderConfigs::class)
            ->callTableAction('setDefault', $openai);

        $this->assertSame($openai->id, AiProviderSetting::current()->default_provider_config_id);
    }

    public function test_action_routing_page_renders_and_saves_per_field_overrides_and_global_defaults(): void
    {
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $anthropic = AiProviderConfig::where('slug', 'anthropic')->firstOrFail();
        $openai->update(['is_enabled' => true]);
        $anthropic->update(['is_enabled' => true]);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/admin/ai-action-routing')
            ->assertOk()
            ->assertSee('SEO Title')
            ->assertSee('Translate');

        Livewire::actingAs($owner)
            ->test(AiActionRouting::class)
            ->fillForm([
                'default_provider_config_id' => $openai->id,
                'failover_enabled' => true,
                'fallback_provider_config_id' => $anthropic->id,
                'overrides' => [
                    'translate' => ['provider' => $anthropic->id, 'model' => null],
                ],
            ])
            ->call('save');

        $settings = AiProviderSetting::current();
        $this->assertSame($openai->id, $settings->default_provider_config_id);
        $this->assertTrue($settings->failover_enabled);
        $this->assertSame($anthropic->id, $settings->fallback_provider_config_id);

        $override = AiActionOverride::where('action_key', 'translate')->firstOrFail();
        $this->assertSame($anthropic->id, $override->ai_provider_config_id);
    }

    public function test_action_routing_page_removes_the_override_row_when_reset_to_use_default(): void
    {
        $anthropic = AiProviderConfig::where('slug', 'anthropic')->firstOrFail();
        $anthropic->update(['is_enabled' => true]);
        AiActionOverride::create(['action_key' => 'translate', 'ai_provider_config_id' => $anthropic->id]);

        Livewire::actingAs($this->owner())
            ->test(AiActionRouting::class)
            ->fillForm([
                'overrides' => [
                    'translate' => ['provider' => null, 'model' => null],
                ],
            ])
            ->call('save');

        $this->assertSame(0, AiActionOverride::where('action_key', 'translate')->count());
    }
}
