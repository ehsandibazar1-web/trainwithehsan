<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPlanner;
use App\Models\ContentPlan;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlannerKanbanTest extends TestCase
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

    public function test_the_planner_page_renders_with_the_kanban_view_by_default(): void
    {
        $this->makePlan();

        $this->actingAs($this->owner())
            ->get('/admin/content-planner')
            ->assertOk()
            ->assertSee('How to escape side control')
            ->assertSee('Idea');
    }

    public function test_switching_views_updates_the_active_view(): void
    {
        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->assertSet('activeView', 'kanban')
            ->call('setView', 'calendar')
            ->assertSet('activeView', 'calendar')
            ->call('setView', 'table')
            ->assertSet('activeView', 'table')
            ->call('setView', 'dashboard')
            ->assertSet('activeView', 'dashboard');
    }

    public function test_moving_a_card_via_move_card_transitions_the_stage(): void
    {
        $plan = $this->makePlan();
        $research = $this->stage('research');

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('moveCard', $plan->id, $research->id);

        $this->assertSame($research->id, $plan->fresh()->workflow_stage_id);
    }

    public function test_filtering_by_priority_narrows_the_kanban_board(): void
    {
        $this->makePlan(['title' => 'High priority idea', 'priority' => ContentPlan::PRIORITY_HIGH]);
        $this->makePlan(['title' => 'Low priority idea', 'priority' => ContentPlan::PRIORITY_LOW]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('filterPriority', 'high')
            ->assertSee('High priority idea')
            ->assertDontSee('Low priority idea');
    }

    public function test_filtering_by_tag_narrows_the_kanban_board(): void
    {
        $tag = Tag::create(['name' => 'Nutrition']);
        $tagged = $this->makePlan(['title' => 'Tagged idea']);
        $tagged->tags()->attach($tag);
        $this->makePlan(['title' => 'Untagged idea']);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('filterTag', (string) $tag->id)
            ->assertSee('Tagged idea')
            ->assertDontSee('Untagged idea');
    }

    public function test_searching_by_title_narrows_the_kanban_board(): void
    {
        $this->makePlan(['title' => 'Guard passing drills']);
        $this->makePlan(['title' => 'Newsletter ideas']);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('search', 'guard')
            ->assertSee('Guard passing drills')
            ->assertDontSee('Newsletter ideas');
    }

    public function test_filtering_by_publication_status_none_shows_only_plans_with_no_linked_content(): void
    {
        $idea = $this->makePlan(['title' => 'Still an idea']);
        $drafted = $this->makePlan(['title' => 'Already drafted']);
        $drafted->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('filterPublicationStatus', 'none')
            ->assertSee('Still an idea')
            ->assertDontSee('Already drafted');
    }

    public function test_reset_filters_clears_all_filter_state(): void
    {
        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('filterPriority', 'high')
            ->set('search', 'something')
            ->call('resetFilters')
            ->assertSet('filterPriority', 'all')
            ->assertSet('search', '');
    }

    public function test_bulk_move_stage_moves_all_selected_cards(): void
    {
        $a = $this->makePlan(['title' => 'Bulk A']);
        $b = $this->makePlan(['title' => 'Bulk B']);
        $research = $this->stage('research');

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('selectedPlanIds', [$a->id, $b->id])
            ->set('bulkStageId', $research->id)
            ->call('bulkMoveStage');

        $this->assertSame($research->id, $a->fresh()->workflow_stage_id);
        $this->assertSame($research->id, $b->fresh()->workflow_stage_id);
    }

    public function test_bulk_set_priority_updates_all_selected_cards(): void
    {
        $a = $this->makePlan(['title' => 'Bulk A']);
        $b = $this->makePlan(['title' => 'Bulk B']);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('selectedPlanIds', [$a->id, $b->id])
            ->set('bulkPriority', ContentPlan::PRIORITY_CRITICAL)
            ->call('bulkSetPriority');

        $this->assertSame(ContentPlan::PRIORITY_CRITICAL, $a->fresh()->priority);
        $this->assertSame(ContentPlan::PRIORITY_CRITICAL, $b->fresh()->priority);
    }

    public function test_bulk_delete_removes_all_selected_cards(): void
    {
        $a = $this->makePlan(['title' => 'Bulk A']);
        $b = $this->makePlan(['title' => 'Bulk B']);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('selectedPlanIds', [$a->id, $b->id])
            ->call('bulkDelete');

        $this->assertDatabaseMissing('content_plans', ['id' => $a->id]);
        $this->assertDatabaseMissing('content_plans', ['id' => $b->id]);
    }
}
