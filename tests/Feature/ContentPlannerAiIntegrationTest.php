<?php

namespace Tests\Feature;

use App\Filament\Pages\AiContentAssistant;
use App\Filament\Pages\ContentPlanner;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlannerAiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function stage(string $slug): WorkflowStage
    {
        return WorkflowStage::findBySlug($slug);
    }

    private function makePlan(array $overrides = []): ContentPlan
    {
        return ContentPlan::create(array_merge([
            'title' => 'How to escape side control',
            'locale' => 'en',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'priority' => ContentPlan::PRIORITY_MEDIUM,
        ], $overrides));
    }

    public function test_generate_draft_materializes_the_content_moves_the_stage_and_redirects_to_the_editor(): void
    {
        $plan = $this->makePlan(['content_type' => 'Article']);

        $component = Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('generateDraft', $plan->id);

        $plan->refresh();
        $this->assertNotNull($plan->contentable_id);
        $this->assertSame($this->stage(WorkflowStage::STAGE_AI_DRAFT)->id, $plan->workflow_stage_id);

        $component->assertRedirect(ArticleResource::getUrl('edit', ['record' => $plan->contentable_id]));
    }

    public function test_generate_draft_is_a_no_op_when_the_plan_already_has_content(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $contentableId = $plan->fresh()->contentable_id;

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('generateDraft', $plan->id);

        $this->assertSame($contentableId, $plan->fresh()->contentable_id);
        $this->assertSame(1, Article::count());
    }

    public function test_ai_assistant_url_is_null_without_a_linked_contentable(): void
    {
        $plan = $this->makePlan();

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class);
        $url = $component->instance()->aiAssistantUrlFor($plan);

        $this->assertNull($url);
    }

    public function test_ai_assistant_url_points_to_the_standalone_page_for_the_linked_article(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->refresh();

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class);
        $url = $component->instance()->aiAssistantUrlFor($plan);

        $this->assertSame(AiContentAssistant::getUrl(['article' => $plan->contentable_id]), $url);
    }

    public function test_ai_assistant_url_points_to_the_standalone_page_for_a_linked_page(): void
    {
        $plan = $this->makePlan(['content_type' => 'Page']);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->refresh();

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class);
        $url = $component->instance()->aiAssistantUrlFor($plan);

        $this->assertSame(AiContentAssistant::getUrl(['page' => $plan->contentable_id]), $url);
    }

    public function test_kanban_shows_generate_draft_for_idea_and_ai_assistant_link_once_materialized(): void
    {
        $idea = $this->makePlan(['title' => 'Pure idea card']);
        $drafted = $this->makePlan(['title' => 'Drafted card']);
        $drafted->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->assertSee('Generate Draft')
            ->assertSee('AI Assistant');
    }
}
