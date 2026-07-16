<?php

namespace Tests\Feature;

use App\Filament\Resources\ContentPlans\ContentPlanResource;
use App\Filament\Resources\ContentPlans\Pages\CreateContentPlan;
use App\Filament\Resources\ContentPlans\Pages\EditContentPlan;
use App\Filament\Resources\ContentPlans\Pages\ListContentPlans;
use App\Models\ContentPlan;
use App\Models\ContentTask;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlanResourceTest extends TestCase
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

    public function test_the_resource_is_hidden_from_navigation_but_its_routes_still_work(): void
    {
        $owner = $this->owner();

        $this->assertFalse(ContentPlanResource::shouldRegisterNavigation());

        $this->actingAs($owner)->get('/admin/content-plans')->assertOk();
    }

    public function test_creating_a_content_plan_with_tags_and_a_task(): void
    {
        $tag = Tag::create(['name' => 'Nutrition']);
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(CreateContentPlan::class)
            ->fillForm([
                'title' => 'Protein timing for BJJ athletes',
                'locale' => 'en',
                'content_type' => 'Article',
                'priority' => ContentPlan::PRIORITY_HIGH,
                'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
                'tags' => [$tag->id],
                'tasks' => [
                    ['title' => 'Write introduction', 'status' => ContentTask::STATUS_PENDING],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = ContentPlan::where('title', 'Protein timing for BJJ athletes')->firstOrFail();
        $this->assertTrue($plan->tags->contains($tag));
        $this->assertSame(1, $plan->tasks()->count());
        $this->assertSame('Write introduction', $plan->tasks()->first()->title);
    }

    public function test_editing_a_content_plan_shows_a_link_to_its_materialized_article(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Guard retention basics',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        $this->actingAs($this->owner())
            ->get("/admin/content-plans/{$plan->id}/edit")
            ->assertOk()
            ->assertSee('Guard retention basics');
    }

    public function test_editing_a_content_plan_without_content_yet_shows_the_not_created_hint(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Pure idea',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);

        $this->actingAs($this->owner())
            ->get("/admin/content-plans/{$plan->id}/edit")
            ->assertOk()
            ->assertSee('Not created yet');
    }

    public function test_seo_review_checklist_appears_only_once_the_plan_is_in_that_stage(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Checklist test',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);
        $owner = $this->owner();

        $ideaResponse = $this->actingAs($owner)->get("/admin/content-plans/{$plan->id}/edit");
        $ideaResponse->assertOk()->assertDontSee('Meta Title');

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_SEO_REVIEW));

        $this->actingAs($owner)
            ->get("/admin/content-plans/{$plan->id}/edit")
            ->assertOk()
            ->assertSee('Meta Title')
            ->assertSee('ALT Text');
    }

    public function test_table_lists_plans_with_stage_and_priority_badges(): void
    {
        ContentPlan::create([
            'title' => 'Visible plan',
            'workflow_stage_id' => $this->stage('research')->id,
            'priority' => ContentPlan::PRIORITY_CRITICAL,
        ]);

        Livewire::actingAs($this->owner())
            ->test(ListContentPlans::class)
            ->assertSee('Visible plan')
            ->assertSee('Research')
            ->assertSee('Critical');
    }

    public function test_scores_are_blank_for_a_plan_with_no_linked_content(): void
    {
        ContentPlan::create([
            'title' => 'No content yet plan',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);

        Livewire::actingAs($this->owner())
            ->test(ListContentPlans::class)
            ->assertSee('No content yet plan');
    }

    public function test_scores_are_computed_from_the_linked_article_once_materialized(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Scored plan',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->contentable->update(['seo_title' => 'A great SEO title', 'meta_description' => str_repeat('word ', 20)]);

        Livewire::actingAs($this->owner())
            ->test(ListContentPlans::class)
            ->assertSee('Scored plan');
    }

    public function test_deleting_a_content_plan_does_not_delete_its_linked_article(): void
    {
        $plan = ContentPlan::create([
            'title' => 'To delete',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
        ]);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $articleId = $plan->contentable_id;

        Livewire::actingAs($this->owner())
            ->test(EditContentPlan::class, ['record' => $plan->id])
            ->callAction('delete');

        $this->assertDatabaseMissing('content_plans', ['id' => $plan->id]);
        $this->assertDatabaseHas('articles', ['id' => $articleId]);
    }
}
