<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPlanner;
use App\Models\ContentPlan;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlannerTableDashboardTest extends TestCase
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

    public function test_table_view_lists_plans_matching_filters(): void
    {
        $this->makePlan(['title' => 'Guard passing drills']);
        $this->makePlan(['title' => 'Off-topic idea']);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'table')
            ->set('search', 'guard')
            ->assertSee('Guard passing drills')
            ->assertDontSee('Off-topic idea');
    }

    public function test_table_view_shows_an_empty_state_when_nothing_matches(): void
    {
        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'table')
            ->assertSee('No content plans match');
    }

    public function test_dashboard_counts_plans_per_stage_correctly(): void
    {
        $this->makePlan(['title' => 'Idea A']);
        $this->makePlan(['title' => 'Idea B']);
        $draft = $this->makePlan(['title' => 'Draft A']);
        $draft->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $review = $this->makePlan(['title' => 'Review A']);
        $review->moveToStage($this->stage(WorkflowStage::STAGE_HUMAN_REVIEW));
        $seoReview = $this->makePlan(['title' => 'SEO Review A']);
        $seoReview->moveToStage($this->stage(WorkflowStage::STAGE_SEO_REVIEW));

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class)->set('activeView', 'dashboard');
        $stats = $component->get('dashboardStats');

        $this->assertSame(2, $stats['ideas']);
        $this->assertSame(1, $stats['drafts']);
        $this->assertSame(2, $stats['reviews']);
        $this->assertSame(0, $stats['scheduled']);
        $this->assertSame(0, $stats['published']);
    }

    public function test_average_publishing_time_is_computed_from_creation_to_first_published_transition(): void
    {
        $plan = $this->makePlan(['title' => 'Timed plan']);
        // برای پیش‌بینی‌پذیر بودن محاسبه‌ی روز، created_at را با query builder (نه save()) عقب می‌بریم
        // تا خودکار به‌روزرسانی timestamp دوباره آن را «الان» نکند
        ContentPlan::whereKey($plan->id)->update(['created_at' => now()->subDays(5)]);
        $plan->refresh();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_PUBLISHED));

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class)->set('activeView', 'dashboard');
        $stats = $component->get('dashboardStats');

        $this->assertEqualsWithDelta(5.0, $stats['avg_publishing_days'], 0.1);
    }

    public function test_average_publishing_time_is_null_when_nothing_has_been_published_yet(): void
    {
        $this->makePlan();

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class)->set('activeView', 'dashboard');
        $stats = $component->get('dashboardStats');

        $this->assertNull($stats['avg_publishing_days']);
    }

    public function test_average_review_time_only_counts_completed_review_visits(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_HUMAN_REVIEW));
        $plan->stageTransitions()->where('to_stage_id', $this->stage(WorkflowStage::STAGE_HUMAN_REVIEW)->id)
            ->update(['created_at' => now()->subDays(3)]);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_SEO_REVIEW));

        // یک کارت دوم هنوز در بازبینی است — نباید در میانگین حساب شود (بازدید تمام‌نشده)
        $stillReviewing = $this->makePlan(['title' => 'Still reviewing']);
        $stillReviewing->moveToStage($this->stage(WorkflowStage::STAGE_HUMAN_REVIEW));

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class)->set('activeView', 'dashboard');
        $stats = $component->get('dashboardStats');

        $this->assertEqualsWithDelta(3.0, $stats['avg_review_days'], 0.1);
    }

    public function test_production_per_month_counts_published_transitions_in_the_current_month(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_PUBLISHED));

        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class)->set('activeView', 'dashboard');
        $months = $component->get('productionPerMonth');

        $this->assertCount(6, $months);
        $this->assertSame(1, $months[5]['count']);
        $this->assertSame(now()->translatedFormat('M Y'), $months[5]['label']);
    }
}
