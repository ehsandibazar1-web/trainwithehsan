<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Models\Article;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OneClickPublishPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function validJson(array $overrides = []): string
    {
        return json_encode(array_merge([
            'language' => 'en',
            'title' => 'Panel Article',
            'content' => '<p>Body.</p>',
            'category' => 'Guides',
            'publish_status' => 'draft',
        ], $overrides));
    }

    public function test_the_page_offers_all_five_formats(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/ai-import')
            ->assertOk()
            ->assertSee('One Click Publish')
            ->assertSee('HTML')
            ->assertSee('XML')
            ->assertSee('Custom [[FIELD]] markers');
    }

    public function test_running_preview_populates_the_manual_corrections_fields(): void
    {
        Livewire::actingAs($this->owner())
            ->test(AiImport::class)
            ->fillForm(['raw' => $this->validJson(), 'format' => 'json'])
            ->call('runPreview')
            ->assertSet('data.corrections.title', 'Panel Article')
            ->assertSet('data.corrections.category', 'Guides')
            ->assertSet('data.corrections.status', 'draft');
    }

    public function test_a_manual_correction_overrides_the_pasted_title_on_import(): void
    {
        Livewire::actingAs($this->owner())
            ->test(AiImport::class)
            ->fillForm([
                'raw' => $this->validJson(),
                'format' => 'json',
                'corrections' => ['title' => 'Corrected Panel Title'],
            ])
            ->call('runImport');

        $this->assertSame('Corrected Panel Title', Article::first()->title);
    }

    public function test_rollback_action_removes_the_imported_article(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(AiImport::class)
            ->fillForm(['raw' => $this->validJson(), 'format' => 'json'])
            ->call('runImport');

        $log = ImportLog::first();
        $this->assertTrue($log->canRollBack());

        Livewire::actingAs($owner)
            ->test(AiImport::class)
            ->call('rollbackLog', $log->id);

        $this->assertSame(0, Article::count());
        $this->assertNotNull($log->fresh()->rolled_back_at);
    }

    public function test_rollback_button_only_shows_for_rollback_eligible_rows(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(AiImport::class)
            ->fillForm(['raw' => $this->validJson(), 'format' => 'json'])
            ->call('runImport');

        $this->actingAs($owner)
            ->get('/admin/ai-import')
            ->assertOk()
            ->assertSee('Roll back');
    }
}
