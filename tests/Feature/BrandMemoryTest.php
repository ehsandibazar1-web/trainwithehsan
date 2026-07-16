<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\BrandMemorySection;
use App\Models\BrandMemoryValue;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\BrandMemory\BrandMemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BrandMemoryTest extends TestCase
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

    private function fakeAnthropicText(string $text): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
            ], 200),
        ]);
    }

    public function test_default_sections_are_seeded_grouped_and_enabled(): void
    {
        $this->assertSame(25, BrandMemorySection::count());
        $this->assertTrue(BrandMemorySection::where('key', 'mission')->first()->is_enabled);
        $this->assertTrue(BrandMemorySection::where('key', 'mission')->first()->is_system);
        $this->assertSame('Identity', BrandMemorySection::where('key', 'brand_name')->first()->group);
    }

    public function test_section_values_relation_and_value_for_locale(): void
    {
        $section = BrandMemorySection::where('key', 'mission')->first();

        BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);
        BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'tr', 'content' => 'Gerçek öz savunma öğretmek.']);

        $section->refresh()->load('values');

        $this->assertCount(2, $section->values);
        $this->assertSame('Teach real self-defense.', $section->valueFor('en')->content);
        $this->assertSame('Gerçek öz savunma öğretmek.', $section->valueFor('tr')->content);
        $this->assertNull($section->valueFor('fa'));
    }

    public function test_custom_sections_can_be_added_and_are_not_system(): void
    {
        $custom = BrandMemorySection::create([
            'key' => 'training_philosophy',
            'label' => 'Training Philosophy',
            'group' => 'Identity',
            'is_enabled' => true,
            'is_system' => false,
            'sort_order' => 99,
        ]);

        $this->assertFalse($custom->is_system);
        $this->assertSame(26, BrandMemorySection::count());
    }

    public function test_updating_a_value_logs_activity_for_version_history(): void
    {
        $section = BrandMemorySection::where('key', 'writing_tone')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Confident and direct.']);

        $value->update(['content' => 'Confident, direct, and encouraging.']);

        $updated = Activity::forSubject($value)->where('log_name', 'brand_memory_value')->where('event', 'updated')->get();

        $this->assertCount(1, $updated);
        $this->assertSame('Confident and direct.', $updated->first()->attribute_changes['old']['content']);
        $this->assertSame('Confident, direct, and encouraging.', $updated->first()->attribute_changes['attributes']['content']);
    }

    public function test_a_no_op_save_does_not_log_a_new_version(): void
    {
        $section = BrandMemorySection::where('key', 'writing_tone')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Confident and direct.']);

        $value->update(['content' => 'Confident and direct.']);

        $this->assertCount(0, Activity::forSubject($value)->where('log_name', 'brand_memory_value')->where('event', 'updated')->get());
    }

    public function test_build_context_returns_empty_string_when_nothing_is_configured(): void
    {
        $this->assertSame('', app(BrandMemoryService::class)->buildContext());
        $this->assertFalse(app(BrandMemoryService::class)->hasContent());
    }

    public function test_build_context_groups_enabled_sections_with_content(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        $tone = BrandMemorySection::where('key', 'writing_tone')->first();

        BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);
        BrandMemoryValue::create(['brand_memory_section_id' => $tone->id, 'locale' => 'en', 'content' => 'Confident and direct.']);

        $context = app(BrandMemoryService::class)->buildContext('en');

        $this->assertStringContainsString('BRAND MEMORY', $context);
        $this->assertStringContainsString('## Identity', $context);
        $this->assertStringContainsString('Mission: Teach real self-defense.', $context);
        $this->assertStringContainsString('## Voice & Audience', $context);
        $this->assertStringContainsString('Writing Tone: Confident and direct.', $context);
        $this->assertTrue(app(BrandMemoryService::class)->hasContent());
    }

    public function test_build_context_excludes_disabled_sections(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        $mission->update(['is_enabled' => false]);
        BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);

        $context = app(BrandMemoryService::class)->buildContext('en');

        $this->assertSame('', $context);
    }

    public function test_build_context_falls_back_to_english_when_the_requested_locale_is_blank(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);

        $context = app(BrandMemoryService::class)->buildContext('tr');

        $this->assertStringContainsString('Teach real self-defense.', $context);
    }

    public function test_generate_prompt_automatically_includes_brand_memory_when_configured(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'Teach real self-defense to everyday people.']);

        $article = $this->makeArticle();
        $this->fakeAnthropicText('A great SEO title.');

        app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Teach real self-defense to everyday people.'));
    }

    public function test_generate_prompt_has_no_brand_memory_block_when_nothing_is_configured(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('A great SEO title.');

        app(ContentAssistantService::class)->generate($article, 'seo_title', 'generate');

        Http::assertSent(fn ($request) => ! str_contains($request->body(), 'BRAND MEMORY'));
    }
}
