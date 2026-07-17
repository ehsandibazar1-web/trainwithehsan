<?php

namespace Tests\Feature;

use App\Filament\Pages\AiActionRouting;
use App\Filament\Resources\AiProviderConfigs\Pages\EditAiProviderConfig;
use App\Jobs\GenerateHeroImage;
use App\Livewire\AiAssistantPanel;
use App\Models\AiGeneration;
use App\Models\AiImageGeneration;
use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\AiAssistant\GenerationApplier;
use App\Services\AiAssistant\ProviderManager;
use App\Services\Media\MediaProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * AI Image Pipeline (Section — hero image generation): provider-agnostic image generation
 * (OpenAI/Gemini only, mirroring the text-provider architecture with the same resolution/
 * failover/usage-logging shape), the GenerateHeroImage job (generate → save to Media Library via
 * the unmodified MediaProcessor → set as featured image → auto-fill ALT/caption/description +
 * blank-only SEO fields through the existing ActionRegistry/AiGeneration/GenerationApplier
 * pipeline), and the Filament UI additions (image_model field, AI Routing "Image Generation"
 * section, per-record prompt fields).
 */
class AiImagePipelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Guard passing is a fundamental BJJ skill.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>Some page content.</p>',
            'status' => 'draft',
        ], $overrides));
    }

    private function makePanel(Article|Page $record): AiAssistantPanel
    {
        $panel = new AiAssistantPanel;
        $panel->record = $record;
        $panel->recordType = $record instanceof Article ? 'Article' : 'Page';

        return $panel;
    }

    private function fakePngBytes(): string
    {
        $im = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean();
        imagedestroy($im);

        return $bytes;
    }

    private function configureImageProvider(string $slug = 'openai', string $model = 'test-image-model'): AiProviderConfig
    {
        $config = AiProviderConfig::where('slug', $slug)->first();
        $config->forceFill(['api_key' => 'sk-test', 'is_enabled' => true, 'image_model' => $model])->save();
        AiProviderSetting::current()->forceFill(['default_image_provider_config_id' => $config->id])->save();

        return $config->fresh();
    }

    private function fakeAnthropicTextFallback(): void
    {
        // متن‌های ALWAYS_FIELDS/BLANK_ONLY_FIELDS در GenerateHeroImage از همان مسیر موجود
        // ContentAssistantService::generate() عبور می‌کنند — چون در این تست‌ها هیچ ارائه‌دهنده‌ی
        // متنیِ پیش‌فرضی تنظیم نشده، ProviderManager به همان مسیر پشتیبان .env (Anthropic) برمی‌گردد.
        // فقط کلید را تنظیم می‌کند — خودِ Http::fake() در هر تست، همراه با فیک تصویرِ همان تست، جدا فراخوانی می‌شود.
        config(['services.anthropic.key' => 'test-key']);
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    // ============ AiProviderConfig image-generation capability ============

    public function test_only_openai_and_gemini_are_image_generation_capable(): void
    {
        $this->assertSame(['openai', 'gemini'], AiProviderConfig::IMAGE_GENERATION_CAPABLE_SLUGS);
    }

    public function test_is_usable_for_image_generation_requires_key_enabled_and_image_model(): void
    {
        $config = AiProviderConfig::where('slug', 'openai')->first();
        $this->assertFalse($config->is_usable_for_image_generation);

        $config->forceFill(['api_key' => 'sk-test', 'is_enabled' => true])->save();
        $this->assertFalse($config->fresh()->is_usable_for_image_generation);

        $config->forceFill(['image_model' => 'gpt-image-1'])->save();
        $this->assertTrue($config->fresh()->is_usable_for_image_generation);
    }

    public function test_grok_is_never_usable_for_image_generation_even_fully_configured(): void
    {
        $config = AiProviderConfig::where('slug', 'grok')->first();
        $config->forceFill(['api_key' => 'x', 'is_enabled' => true, 'image_model' => 'whatever'])->save();

        $this->assertFalse($config->fresh()->is_usable_for_image_generation);
    }

    // ============ OpenAiProvider::generateImage() ============

    public function test_openai_generate_image_decodes_b64_json_response(): void
    {
        $this->configureImageProvider('openai');
        $png = $this->fakePngBytes();

        Http::fake([
            'api.openai.com/*' => Http::response([
                'data' => [['b64_json' => base64_encode($png), 'revised_prompt' => 'A refined prompt']],
            ]),
        ]);

        $result = app(ProviderManager::class)->generateImage('A hero image prompt');

        $this->assertSame($png, $result['bytes']);
        $this->assertSame('openai', $result['provider_slug']);
        $this->assertSame('A refined prompt', $result['revised_prompt']);
    }

    public function test_openai_generate_image_downloads_url_response(): void
    {
        $this->configureImageProvider('openai');
        $png = $this->fakePngBytes();

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['url' => 'https://cdn.example.com/img.png']]]),
            'cdn.example.com/*' => Http::response($png, 200, ['Content-Type' => 'image/png']),
        ]);

        $result = app(ProviderManager::class)->generateImage('A hero image prompt');

        $this->assertSame($png, $result['bytes']);
    }

    // ============ GeminiProvider::generateImage() ============

    public function test_gemini_generate_image_decodes_predictions_response(): void
    {
        $this->configureImageProvider('gemini');
        $png = $this->fakePngBytes();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'predictions' => [['bytesBase64Encoded' => base64_encode($png), 'mimeType' => 'image/png']],
            ]),
        ]);

        $result = app(ProviderManager::class)->generateImage('A hero image prompt');

        $this->assertSame($png, $result['bytes']);
        $this->assertSame('gemini', $result['provider_slug']);
        $this->assertNull($result['revised_prompt']);
    }

    // ============ ProviderManager::generateImage() resolution / failover ============

    public function test_resolve_image_provider_is_null_when_nothing_configured(): void
    {
        $this->assertNull(app(ProviderManager::class)->resolveImageProvider());
    }

    public function test_generate_image_throws_when_no_provider_configured(): void
    {
        $this->expectException(\RuntimeException::class);
        app(ProviderManager::class)->generateImage('prompt');
    }

    public function test_generate_image_logs_usage_on_success(): void
    {
        $this->configureImageProvider('openai');
        $png = $this->fakePngBytes();
        Http::fake(['api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]])]);

        app(ProviderManager::class)->generateImage('prompt', [], 'Article', 1);

        $this->assertDatabaseHas('ai_usage_logs', [
            'provider_slug' => 'openai', 'action_key' => 'image_generation', 'status' => 'success',
            'content_type' => 'Article', 'content_id' => 1,
        ]);
    }

    public function test_generate_image_fails_over_to_the_fallback_provider_when_primary_fails(): void
    {
        $this->configureImageProvider('openai');
        $fallback = AiProviderConfig::where('slug', 'gemini')->first();
        $fallback->forceFill(['api_key' => 'sk-test', 'is_enabled' => true, 'image_model' => 'imagen-3'])->save();
        AiProviderSetting::current()->forceFill([
            'image_failover_enabled' => true,
            'fallback_image_provider_config_id' => $fallback->id,
        ])->save();

        $png = $this->fakePngBytes();
        Http::fake([
            'api.openai.com/*' => Http::response('', 500),
            'generativelanguage.googleapis.com/*' => Http::response(['predictions' => [['bytesBase64Encoded' => base64_encode($png)]]]),
        ]);

        $result = app(ProviderManager::class)->generateImage('prompt');

        $this->assertSame('gemini', $result['provider_slug']);
        $this->assertDatabaseHas('ai_usage_logs', ['provider_slug' => 'openai', 'status' => 'failed', 'action_key' => 'image_generation']);
        $this->assertDatabaseHas('ai_usage_logs', ['provider_slug' => 'gemini', 'status' => 'success', 'action_key' => 'image_generation']);
    }

    public function test_generate_image_throws_when_all_candidates_fail(): void
    {
        $this->configureImageProvider('openai');
        Http::fake(['api.openai.com/*' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);
        app(ProviderManager::class)->generateImage('prompt');
    }

    public function test_a_provider_selected_but_not_image_capable_is_never_returned_as_the_resolved_provider(): void
    {
        $grok = AiProviderConfig::where('slug', 'grok')->first();
        $grok->forceFill(['api_key' => 'x', 'is_enabled' => true, 'image_model' => 'whatever'])->save();
        AiProviderSetting::current()->forceFill(['default_image_provider_config_id' => $grok->id])->save();

        $this->assertNull(app(ProviderManager::class)->resolveImageProvider());

        $this->expectException(\RuntimeException::class);
        app(ProviderManager::class)->generateImage('prompt');
    }

    // ============ GenerateHeroImage job ============

    public function test_generate_hero_image_end_to_end_saves_media_sets_featured_image_and_auto_fills_metadata(): void
    {
        Storage::fake('public');
        $this->configureImageProvider('openai');
        $this->fakeAnthropicTextFallback();

        $article = $this->makeArticle(['seo_title' => 'Existing SEO title']);
        $png = $this->fakePngBytes();

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]]),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Generated text']]], 200),
        ]);

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class),
            app(ContentAssistantService::class),
            app(MediaProcessor::class),
            app(GenerationApplier::class),
        );

        $imageGeneration->refresh();
        $this->assertSame('completed', $imageGeneration->status);
        $this->assertSame('openai', $imageGeneration->provider_slug);
        $this->assertNotNull($imageGeneration->media_id);
        $this->assertNotNull($imageGeneration->prompt_used);

        $article->refresh();
        $media = Media::find($imageGeneration->media_id);
        $this->assertSame($article->image_path, $media->disk_path);
        $this->assertSame('AI Generated', $media->folder->name);

        // ALWAYS_FIELDS: alt_text/caption/description are always (re)generated on a brand-new image
        $media->refresh();
        $this->assertNotNull($media->alt_text);
        $this->assertNotNull($media->caption);
        $this->assertNotNull($media->description);

        // BLANK_ONLY_FIELDS: existing seo_title must survive untouched; blank meta_description gets filled
        $this->assertSame('Existing SEO title', $article->seo_title);
        $this->assertNotNull($article->meta_description);

        // Every auto field went through the real AiGeneration + GenerationApplier pipeline (History tab)
        $altGeneration = AiGeneration::where('content_id', $article->id)->where('field', 'alt_text')->sole();
        $this->assertSame('completed', $altGeneration->status);
        $this->assertNotNull($altGeneration->applied_at);
    }

    public function test_generate_hero_image_builds_a_prompt_automatically_when_none_is_set(): void
    {
        Storage::fake('public');
        $this->configureImageProvider('openai');
        $this->fakeAnthropicTextFallback();

        $article = $this->makeArticle();
        $png = $this->fakePngBytes();

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]]),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Generated text']]], 200),
        ]);

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $this->assertStringContainsString('Guard Passing Basics', $imageGeneration->fresh()->prompt_used);
    }

    public function test_generate_hero_image_uses_the_records_own_prompt_when_set(): void
    {
        Storage::fake('public');
        $this->configureImageProvider('openai');
        $this->fakeAnthropicTextFallback();

        $article = $this->makeArticle(['hero_image_prompt' => 'A very specific custom prompt.']);
        $png = $this->fakePngBytes();

        Http::fake([
            'api.openai.com/*' => Http::response(['data' => [['b64_json' => base64_encode($png)]]]),
            'api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'Generated text']]], 200),
        ]);

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $this->assertSame('A very specific custom prompt.', $imageGeneration->fresh()->prompt_used);
    }

    public function test_generate_hero_image_fails_gracefully_when_no_provider_is_configured(): void
    {
        $article = $this->makeArticle();

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $imageGeneration->refresh();
        $this->assertSame('failed', $imageGeneration->status);
        $this->assertNotNull($imageGeneration->error);
        $this->assertNull($article->fresh()->image_path);
    }

    public function test_generate_hero_image_skips_a_generation_cancelled_before_it_started(): void
    {
        $this->configureImageProvider('openai');
        $article = $this->makeArticle();

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'cancelled',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $this->assertSame('cancelled', $imageGeneration->fresh()->status);
        $this->assertNull($article->fresh()->image_path);
    }

    public function test_generate_hero_image_honors_a_cancel_that_happens_during_the_api_call(): void
    {
        Storage::fake('public');
        $this->configureImageProvider('openai');
        $article = $this->makeArticle();
        $png = $this->fakePngBytes();

        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        // Http::fake با یک closure — دقیقاً درست قبل از برگرداندنِ پاسخِ API، رکورد را cancelled
        // می‌کند تا چک‌پوینتِ دومِ GenerateHeroImage (بعد از تماس API، قبل از ذخیره) تست شود
        Http::fake([
            'api.openai.com/*' => function () use ($imageGeneration, $png) {
                $imageGeneration->update(['status' => 'cancelled']);

                return Http::response(['data' => [['b64_json' => base64_encode($png)]]]);
            },
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $this->assertSame('cancelled', $imageGeneration->fresh()->status);
        $this->assertNull($imageGeneration->fresh()->media_id);
        $this->assertNull($article->fresh()->image_path);
        $this->assertSame(0, Media::count());
    }

    public function test_generate_hero_image_fails_when_the_record_no_longer_exists(): void
    {
        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => 999999, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        (new GenerateHeroImage($imageGeneration->id))->handle(
            app(ProviderManager::class), app(ContentAssistantService::class), app(MediaProcessor::class), app(GenerationApplier::class)
        );

        $imageGeneration->refresh();
        $this->assertSame('failed', $imageGeneration->status);
        $this->assertStringContainsString('no longer exists', $imageGeneration->error);
    }

    // ============ AiImageGeneration model ============

    public function test_is_cancellable_is_true_only_while_queued_or_processing(): void
    {
        $queued = AiImageGeneration::create(['content_type' => 'Article', 'content_id' => 1, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued']);
        $processing = AiImageGeneration::create(['content_type' => 'Article', 'content_id' => 1, 'prompt_field' => 'hero_image_prompt', 'status' => 'processing']);
        $completed = AiImageGeneration::create(['content_type' => 'Article', 'content_id' => 1, 'prompt_field' => 'hero_image_prompt', 'status' => 'completed']);

        $this->assertTrue($queued->isCancellable());
        $this->assertTrue($processing->isCancellable());
        $this->assertFalse($completed->isCancellable());
    }

    public function test_scope_for_record_filters_by_content_type_and_id(): void
    {
        AiImageGeneration::create(['content_type' => 'Article', 'content_id' => 1, 'prompt_field' => 'hero_image_prompt', 'status' => 'completed']);
        AiImageGeneration::create(['content_type' => 'Article', 'content_id' => 2, 'prompt_field' => 'hero_image_prompt', 'status' => 'completed']);
        AiImageGeneration::create(['content_type' => 'Page', 'content_id' => 1, 'prompt_field' => 'hero_image_prompt', 'status' => 'completed']);

        $this->assertSame(1, AiImageGeneration::forRecord('Article', 1)->count());
    }

    // ============ AiAssistantPanel wiring ============

    public function test_generate_hero_image_dispatches_the_job_when_a_provider_is_configured(): void
    {
        Bus::fake();
        $this->configureImageProvider('openai');
        $article = $this->makeArticle();

        $this->makePanel($article)->generateHeroImage();

        $this->assertDatabaseHas('ai_image_generations', ['content_type' => 'Article', 'content_id' => $article->id, 'status' => 'queued']);
        Bus::assertDispatched(GenerateHeroImage::class);
    }

    public function test_generate_hero_image_does_nothing_when_no_provider_is_configured(): void
    {
        Bus::fake();
        $article = $this->makeArticle();

        $this->makePanel($article)->generateHeroImage();

        $this->assertSame(0, AiImageGeneration::count());
        Bus::assertNotDispatched(GenerateHeroImage::class);
    }

    public function test_can_generate_images_property_reflects_provider_configuration(): void
    {
        $article = $this->makeArticle();
        $panel = $this->makePanel($article);

        $this->assertFalse($panel->getCanGenerateImagesProperty());

        $this->configureImageProvider('openai');
        $this->assertTrue($panel->getCanGenerateImagesProperty());
    }

    public function test_cancel_image_generation_flags_a_queued_generation_as_cancelled(): void
    {
        $article = $this->makeArticle();
        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'queued',
        ]);

        $this->makePanel($article)->cancelImageGeneration($imageGeneration->id);

        $this->assertSame('cancelled', $imageGeneration->fresh()->status);
    }

    public function test_cancel_image_generation_does_nothing_to_an_already_completed_generation(): void
    {
        $article = $this->makeArticle();
        $imageGeneration = AiImageGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'prompt_field' => 'hero_image_prompt', 'status' => 'completed',
        ]);

        $this->makePanel($article)->cancelImageGeneration($imageGeneration->id);

        $this->assertSame('completed', $imageGeneration->fresh()->status);
    }

    // ============ Filament UI ============

    public function test_image_model_field_is_only_visible_for_openai_and_gemini(): void
    {
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $anthropic = AiProviderConfig::where('slug', 'anthropic')->firstOrFail();
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(EditAiProviderConfig::class, ['record' => $openai->id])
            ->assertFormFieldIsVisible('image_model');

        Livewire::actingAs($owner)
            ->test(EditAiProviderConfig::class, ['record' => $anthropic->id])
            ->assertFormFieldIsHidden('image_model');
    }

    public function test_action_routing_page_saves_image_generation_provider_settings(): void
    {
        // Anthropic باید فعال بماند وگرنه سکشن «Global defaults» (بخش موجود، نامرتبط با این تست)
        // چون default_provider_config_id سیدشده هنوز به همان ردیف غیرفعال اشاره می‌کند، اعتبارسنجیِ
        // Select آن (Filament::Select::getInValidationRuleValues) کل فرم را رد می‌کند
        AiProviderConfig::where('slug', 'anthropic')->update(['is_enabled' => true]);
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $gemini = AiProviderConfig::where('slug', 'gemini')->firstOrFail();
        $openai->update(['is_enabled' => true, 'image_model' => 'gpt-image-1']);
        $gemini->update(['is_enabled' => true, 'image_model' => 'imagen-3.0-generate-002']);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/admin/ai-action-routing')
            ->assertOk()
            ->assertSee('Image Generation')
            ->assertSee('Hero Image Prompt');

        Livewire::actingAs($owner)
            ->test(AiActionRouting::class)
            ->fillForm([
                'default_image_provider_config_id' => $openai->id,
                'image_failover_enabled' => true,
                'fallback_image_provider_config_id' => $gemini->id,
            ])
            ->call('save');

        $settings = AiProviderSetting::current();
        $this->assertSame($openai->id, $settings->default_image_provider_config_id);
        $this->assertTrue($settings->image_failover_enabled);
        $this->assertSame($gemini->id, $settings->fallback_image_provider_config_id);
    }

    public function test_action_routing_page_clears_the_fallback_image_provider_when_failover_is_disabled(): void
    {
        AiProviderConfig::where('slug', 'anthropic')->update(['is_enabled' => true]);
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $gemini = AiProviderConfig::where('slug', 'gemini')->firstOrFail();
        AiProviderSetting::current()->forceFill([
            'default_image_provider_config_id' => $openai->id,
            'image_failover_enabled' => true,
            'fallback_image_provider_config_id' => $gemini->id,
        ])->save();

        Livewire::actingAs($this->owner())
            ->test(AiActionRouting::class)
            ->fillForm(['image_failover_enabled' => false])
            ->call('save');

        $this->assertNull(AiProviderSetting::current()->fallback_image_provider_config_id);
    }

    public function test_article_form_exposes_the_four_image_prompt_fields(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/articles/create')
            ->assertOk()
            ->assertSee('Hero image prompt')
            ->assertSee('Thumbnail image prompt')
            ->assertSee('Open Graph image prompt')
            ->assertSee('Social image prompt');
    }

    public function test_page_form_exposes_the_four_image_prompt_fields(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/pages/create')
            ->assertOk()
            ->assertSee('Hero image prompt')
            ->assertSee('Thumbnail image prompt')
            ->assertSee('Open Graph image prompt')
            ->assertSee('Social image prompt');
    }
}
