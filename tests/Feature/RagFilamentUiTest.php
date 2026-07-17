<?php

namespace Tests\Feature;

use App\Filament\Pages\AiActionRouting;
use App\Filament\Resources\AiProviderConfigs\Pages\EditAiProviderConfig;
use App\Filament\Resources\KnowledgeEntries\Pages\CreateKnowledgeEntry;
use App\Filament\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use App\Filament\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use App\Jobs\IndexKnowledgeContent;
use App\Jobs\RebuildKnowledgeIndex;
use App\Models\AiProviderConfig;
use App\Models\AiProviderSetting;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class RagFilamentUiTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    // --- AiProviderConfigForm: embedding_model field ------------------------------------------

    public function test_embedding_model_field_is_visible_and_saveable_on_openai_row(): void
    {
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();

        Livewire::actingAs($this->owner())
            ->test(EditAiProviderConfig::class, ['record' => $openai->id])
            ->assertFormFieldExists('embedding_model')
            ->fillForm(['embedding_model' => 'text-embedding-3-small'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('text-embedding-3-small', $openai->fresh()->embedding_model);
    }

    public function test_embedding_model_field_is_hidden_on_anthropic_row(): void
    {
        $anthropic = AiProviderConfig::where('slug', 'anthropic')->firstOrFail();

        Livewire::actingAs($this->owner())
            ->test(EditAiProviderConfig::class, ['record' => $anthropic->id])
            ->assertFormFieldDoesNotExist('embedding_model');
    }

    // --- AiActionRouting: Embeddings section ----------------------------------------------------

    public function test_ai_routing_page_saves_the_chosen_embedding_provider(): void
    {
        $openai = AiProviderConfig::where('slug', 'openai')->firstOrFail();
        $openai->update(['is_enabled' => true, 'api_key' => 'sk-test', 'embedding_model' => 'text-embedding-3-small']);
        $owner = $this->owner();

        $this->actingAs($owner)
            ->get('/admin/ai-action-routing')
            ->assertOk()
            ->assertSee('Embeddings');

        // پیکربندی seed این صفحه پیش‌فرض «default provider» را روی anthropic (که در این تست
        // فعال نیست) می‌گذارد — باید صریحاً null شود، وگرنه Select آن را نامعتبر می‌داند (همان
        // چیزی که AiProviderSettingsTest.php هم با فعال‌کردن anthropic از این مشکل دور می‌ماند)
        Livewire::actingAs($owner)
            ->test(AiActionRouting::class)
            ->fillForm([
                'default_provider_config_id' => null,
                'embedding_provider_config_id' => $openai->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($openai->id, AiProviderSetting::current()->embedding_provider_config_id);
    }

    public function test_ai_routing_page_only_offers_embedding_capable_providers(): void
    {
        Livewire::actingAs($this->owner())
            ->test(AiActionRouting::class)
            ->assertFormFieldExists('embedding_provider_config_id');

        $slugsOfferedForEmbedding = AiProviderConfig::EMBEDDING_CAPABLE_SLUGS;
        $this->assertSame(['openai', 'gemini'], $slugsOfferedForEmbedding);
    }

    // --- KnowledgeEntryForm: add a website page by URL -------------------------------------------

    public function test_creating_an_entry_with_a_website_url_creates_a_url_sourced_attachment(): void
    {
        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(CreateKnowledgeEntry::class)
            ->fillForm([
                'title' => 'Policy Page',
                'category' => 'Policies',
                'locale' => 'en',
                'content' => 'Summary of the policy.',
                'status' => KnowledgeEntry::STATUS_ACTIVE,
                'new_website_url' => 'https://example.com/policy',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = KnowledgeEntry::where('title', 'Policy Page')->firstOrFail();
        $attachment = $entry->attachments()->firstOrFail();

        $this->assertTrue($attachment->isUrlSource());
        $this->assertSame('https://example.com/policy', $attachment->source_url);
        $this->assertSame('', $attachment->disk_path);
    }

    public function test_creating_an_entry_without_a_website_url_creates_no_attachment(): void
    {
        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(CreateKnowledgeEntry::class)
            ->fillForm([
                'title' => 'No URL Entry',
                'category' => 'General',
                'locale' => 'en',
                'content' => 'Some content.',
                'status' => KnowledgeEntry::STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = KnowledgeEntry::where('title', 'No URL Entry')->firstOrFail();
        $this->assertSame(0, $entry->attachments()->count());
    }

    public function test_editing_an_entry_and_adding_a_website_url_creates_the_attachment(): void
    {
        Bus::fake();

        $entry = KnowledgeEntry::create([
            'title' => 'Existing Entry', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        Livewire::actingAs($this->owner())
            ->test(EditKnowledgeEntry::class, ['record' => $entry->id])
            ->fillForm(['new_website_url' => 'https://example.com/added-later'])
            ->call('save')
            ->assertHasNoFormErrors();

        $attachment = $entry->fresh()->attachments()->firstOrFail();
        $this->assertSame('https://example.com/added-later', $attachment->source_url);
    }

    // --- Reindex actions -------------------------------------------------------------------------

    public function test_edit_knowledge_entry_reindex_action_dispatches_jobs_for_entry_and_attachments(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Reindex Me', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);
        $attachment = KnowledgeEntryAttachment::createFromUrl($entry, 'https://example.com/a');

        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(EditKnowledgeEntry::class, ['record' => $entry->id])
            ->callAction('reindex');

        Bus::assertDispatched(IndexKnowledgeContent::class, 2);
    }

    public function test_knowledge_entries_table_row_reindex_action_dispatches_job(): void
    {
        $entry = KnowledgeEntry::create([
            'title' => 'Row Reindex', 'category' => 'General', 'locale' => 'en',
            'content' => 'Content.', 'status' => KnowledgeEntry::STATUS_ACTIVE,
        ]);

        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(ListKnowledgeEntries::class)
            ->callTableAction('reindex', $entry);

        Bus::assertDispatched(IndexKnowledgeContent::class);
    }

    public function test_rebuild_all_indexes_action_dispatches_rebuild_job(): void
    {
        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(ListKnowledgeEntries::class)
            ->callAction('rebuildAllIndexes');

        Bus::assertDispatched(RebuildKnowledgeIndex::class);
    }
}
