<?php

namespace Tests\Feature;

use App\Filament\Resources\WorkflowStages\Pages\CreateWorkflowStage;
use App\Filament\Resources\WorkflowStages\Pages\EditWorkflowStage;
use App\Filament\Resources\WorkflowStages\Pages\ListWorkflowStages;
use App\Models\ContentPlan;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowStageResourceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_the_eight_default_stages_are_listed(): void
    {
        $this->actingAs($this->owner())
            ->get('/admin/workflow-stages')
            ->assertOk()
            ->assertSee('Idea')
            ->assertSee('SEO Review')
            ->assertSee('Archived');
    }

    public function test_creating_a_custom_stage_works(): void
    {
        Livewire::actingAs($this->owner())
            ->test(CreateWorkflowStage::class)
            ->fillForm(['label' => 'Editorial Board Approval', 'slug' => 'editorial_board_approval', 'sort_order' => 99])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('workflow_stages', ['slug' => 'editorial_board_approval']);
    }

    public function test_setting_a_new_stage_as_default_unsets_the_previous_default(): void
    {
        $owner = $this->owner();

        Livewire::actingAs($owner)
            ->test(CreateWorkflowStage::class)
            ->fillForm(['label' => 'Backlog', 'slug' => 'backlog', 'is_default' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue(WorkflowStage::where('slug', 'backlog')->first()->is_default);
        $this->assertFalse(WorkflowStage::findBySlug(WorkflowStage::STAGE_IDEA)->fresh()->is_default);
        $this->assertSame(1, WorkflowStage::where('is_default', true)->count());
    }

    public function test_delete_action_is_hidden_for_a_stage_that_still_has_cards(): void
    {
        $ideaStage = WorkflowStage::findBySlug(WorkflowStage::STAGE_IDEA);
        ContentPlan::create(['title' => 'In use', 'workflow_stage_id' => $ideaStage->id]);

        Livewire::actingAs($this->owner())
            ->test(ListWorkflowStages::class)
            ->assertTableActionHidden('delete', $ideaStage);
    }

    public function test_delete_action_is_visible_for_an_empty_stage(): void
    {
        $research = WorkflowStage::findBySlug('research');

        Livewire::actingAs($this->owner())
            ->test(ListWorkflowStages::class)
            ->assertTableActionVisible('delete', $research);
    }

    public function test_checklist_items_can_be_edited_for_a_stage(): void
    {
        $seoReview = WorkflowStage::findBySlug(WorkflowStage::STAGE_SEO_REVIEW);

        Livewire::actingAs($this->owner())
            ->test(EditWorkflowStage::class, ['record' => $seoReview->id])
            ->fillForm([
                'checklist_items' => [
                    ['key' => 'meta_title', 'label' => 'Meta Title'],
                    ['key' => 'og_image', 'label' => 'Social Share Image'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertCount(2, $seoReview->fresh()->checklist_items);
    }
}
