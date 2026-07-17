<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAgentDashboard;
use App\Jobs\RunAgentAudit;
use App\Models\AiGeneration;
use App\Models\AiRecommendation;
use App\Models\Article;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

class AiAgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en', 'title' => 'Guard Passing Basics', 'slug' => 'guard-passing-basics-'.uniqid(),
            'body' => '<p>Body content.</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now(),
        ], $overrides));
    }

    public function test_dashboard_is_reachable_by_the_admin(): void
    {
        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->assertSuccessful();
    }

    public function test_run_audit_now_dispatches_the_queued_job(): void
    {
        Bus::fake();

        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('runAuditNow')
            ->assertSuccessful();

        Bus::assertDispatched(RunAgentAudit::class);
    }

    public function test_set_category_switches_the_active_category(): void
    {
        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('setCategory', 'thin_content')
            ->assertSet('activeCategory', 'thin_content');
    }

    public function test_category_counts_only_include_pending_recommendations(): void
    {
        $article = $this->makeArticle();
        AiRecommendation::create([
            'category' => 'thin_content', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'status' => 'pending',
        ]);
        AiRecommendation::create([
            'category' => 'thin_content', 'content_type' => 'Article', 'content_id' => $article->id + 1,
            'title' => 'y', 'detail' => 'y', 'status' => 'applied',
        ]);

        $component = Livewire::actingAs($this->owner())->test(AiAgentDashboard::class);

        $this->assertSame(1, $component->instance()->categoryCounts['thin_content']);
        $this->assertSame(1, $component->instance()->totalApplied);
    }

    public function test_queue_fix_queues_a_generation_for_a_fixable_recommendation(): void
    {
        Bus::fake();
        $article = $this->makeArticle();
        $recommendation = AiRecommendation::create([
            'category' => 'poor_seo', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'field', 'fix_field' => 'seo_title', 'fix_mode' => 'generate',
        ]);

        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('queueFix', $recommendation->id)
            ->assertSuccessful();

        $this->assertNotNull($recommendation->fresh()->ai_generation_id);
    }

    public function test_approve_fix_applies_a_completed_generation(): void
    {
        $article = $this->makeArticle(['seo_title' => null]);
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate',
            'status' => 'completed', 'result' => 'Generated Title',
        ]);
        $recommendation = AiRecommendation::create([
            'category' => 'poor_seo', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x', 'fix_type' => 'field', 'fix_field' => 'seo_title', 'fix_mode' => 'generate',
            'ai_generation_id' => $generation->id,
        ]);

        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('approveFix', $recommendation->id)
            ->assertSuccessful();

        $this->assertSame('Generated Title', $article->fresh()->seo_title);
        $this->assertSame('applied', $recommendation->fresh()->status);
    }

    public function test_reject_fix_dismisses_a_recommendation(): void
    {
        $article = $this->makeArticle();
        $recommendation = AiRecommendation::create([
            'category' => 'orphan_pages', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'x', 'detail' => 'x',
        ]);

        Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('rejectFix', $recommendation->id)
            ->assertSuccessful();

        $this->assertSame('rejected', $recommendation->fresh()->status);
    }

    public function test_findings_are_filtered_by_search(): void
    {
        $article = $this->makeArticle();
        AiRecommendation::create([
            'category' => 'thin_content', 'content_type' => 'Article', 'content_id' => $article->id,
            'title' => 'Findable Title', 'detail' => 'x', 'status' => 'pending',
        ]);

        $component = Livewire::actingAs($this->owner())
            ->test(AiAgentDashboard::class)
            ->call('setCategory', 'thin_content')
            ->set('search', 'Findable');

        $this->assertCount(1, $component->instance()->findings);

        $component->set('search', 'Nonexistent');
        $this->assertCount(0, $component->instance()->findings);
    }
}
