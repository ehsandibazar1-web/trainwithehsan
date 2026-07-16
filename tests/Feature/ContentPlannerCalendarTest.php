<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPlanner;
use App\Filament\Pages\EditorialCalendar;
use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\Page;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentPlannerCalendarTest extends TestCase
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

    public function test_the_old_editorial_calendar_url_redirects_to_the_planners_calendar_view(): void
    {
        $response = $this->actingAs($this->owner())->get('/admin/editorial-calendar');

        $response->assertRedirect();
        $this->assertStringContainsString('view=calendar', $response->headers->get('Location'));
    }

    public function test_editorial_calendar_is_hidden_from_navigation(): void
    {
        $this->assertFalse(EditorialCalendar::shouldRegisterNavigation());
    }

    public function test_calendar_view_shows_an_article_scheduled_this_month(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'Scheduled article', 'slug' => 'scheduled-'.uniqid(),
            'body' => '', 'author_name' => 'Ehsan', 'status' => 'scheduled',
            'published_at' => now()->startOfMonth()->addDays(3),
        ]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'calendar')
            ->assertSee('Scheduled article');
    }

    public function test_calendar_view_shows_a_page_scheduled_this_month(): void
    {
        Page::create([
            'locale' => 'en', 'title' => 'Scheduled page', 'slug' => 'scheduled-page-'.uniqid(),
            'body' => '', 'status' => 'scheduled',
            'published_at' => now()->startOfMonth()->addDays(5),
        ]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'calendar')
            ->assertSee('Scheduled page');
    }

    public function test_calendar_view_shows_a_planned_idea_with_no_content_yet(): void
    {
        ContentPlan::create([
            'title' => 'Future idea',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'planned_publish_at' => now()->startOfMonth()->addDays(10),
        ]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'calendar')
            ->assertSee('Future idea');
    }

    public function test_calendar_view_shows_a_draft_deadline(): void
    {
        ContentPlan::create([
            'title' => 'Deadline idea',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'due_at' => now()->startOfMonth()->addDays(7),
        ]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'calendar')
            ->assertSee('Deadline idea');
    }

    public function test_calendar_view_hides_deadlines_for_already_published_plans(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Already published idea',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'due_at' => now()->startOfMonth()->addDays(7),
        ]);
        $plan->update(['workflow_stage_id' => $this->stage(WorkflowStage::STAGE_PUBLISHED)->id]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->set('activeView', 'calendar')
            ->assertDontSee('Already published idea');
    }

    public function test_reschedule_item_content_updates_the_articles_published_at_date_preserving_time(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'Reschedule me', 'slug' => 'reschedule-'.uniqid(),
            'body' => '', 'author_name' => 'Ehsan', 'status' => 'scheduled',
            'published_at' => now()->setTime(14, 30),
        ]);
        $newDate = now()->addDays(4)->format('Y-m-d');

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('rescheduleItem', 'content', 'Article', $article->id, $newDate);

        $fresh = $article->fresh();
        $this->assertSame($newDate, $fresh->published_at->format('Y-m-d'));
        $this->assertSame('14:30:00', $fresh->published_at->format('H:i:s'));
    }

    public function test_reschedule_item_content_is_a_no_op_when_the_article_has_no_published_at(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'No date yet', 'slug' => 'no-date-'.uniqid(),
            'body' => '', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('rescheduleItem', 'content', 'Article', $article->id, now()->addDay()->format('Y-m-d'));

        $this->assertNull($article->fresh()->published_at);
    }

    public function test_reschedule_item_planned_updates_the_plans_planned_publish_at(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Move me',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'planned_publish_at' => now(),
        ]);
        $newDate = now()->addWeek()->format('Y-m-d');

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('rescheduleItem', 'planned', null, $plan->id, $newDate);

        $this->assertSame($newDate, $plan->fresh()->planned_publish_at->format('Y-m-d'));
    }

    public function test_reschedule_item_deadline_updates_the_plans_due_at(): void
    {
        $plan = ContentPlan::create([
            'title' => 'Deadline move',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'due_at' => now(),
        ]);
        $newDate = now()->addDays(2)->format('Y-m-d');

        Livewire::actingAs($this->owner())
            ->test(ContentPlanner::class)
            ->call('rescheduleItem', 'deadline', null, $plan->id, $newDate);

        $this->assertSame($newDate, $plan->fresh()->due_at->format('Y-m-d'));
    }

    public function test_month_navigation_moves_forward_and_back_and_today_resets(): void
    {
        $component = Livewire::actingAs($this->owner())->test(ContentPlanner::class);
        $currentMonth = (int) now()->format('n');

        $component->call('nextMonth');
        $expectedNext = (int) now()->addMonthNoOverflow()->format('n');
        $component->assertSet('month', $expectedNext);

        $component->call('goToday');
        $component->assertSet('month', $currentMonth);
    }
}
