<?php

namespace Tests\Feature;

use App\Console\Commands\PublishDueArticles;
use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\ContentTask;
use App\Models\Page;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPlanTest extends TestCase
{
    use RefreshDatabase;

    private function stage(string $slug): WorkflowStage
    {
        return WorkflowStage::findBySlug($slug);
    }

    private function makePlan(array $overrides = []): ContentPlan
    {
        return ContentPlan::create(array_merge([
            'title' => 'How to escape side control',
            'locale' => 'en',
            'category' => 'Technique',
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'priority' => ContentPlan::PRIORITY_MEDIUM,
        ], $overrides));
    }

    public function test_the_eight_default_stages_are_seeded_in_order_with_the_seo_review_checklist(): void
    {
        $slugs = WorkflowStage::orderBy('sort_order')->pluck('slug')->all();

        $this->assertSame(
            ['idea', 'research', 'ai_draft', 'human_review', 'seo_review', 'scheduled', 'published', 'archived'],
            $slugs
        );

        $this->assertTrue($this->stage('idea')->is_default);
        $this->assertTrue($this->stage('archived')->is_terminal);

        $seo = $this->stage('seo_review');
        $this->assertCount(7, $seo->checklist_items);
        $this->assertSame('meta_title', $seo->checklist_items[0]['key']);
    }

    public function test_workflow_stage_default_falls_back_to_lowest_sort_order_if_no_row_is_marked_default(): void
    {
        WorkflowStage::query()->update(['is_default' => false]);

        $default = WorkflowStage::default();

        $this->assertSame('idea', $default->slug);
    }

    public function test_move_to_stage_records_a_transition_and_is_a_no_op_for_the_same_stage(): void
    {
        $plan = $this->makePlan();
        $research = $this->stage('research');

        $plan->moveToStage($research);

        $this->assertSame($research->id, $plan->fresh()->workflow_stage_id);
        $this->assertSame(1, $plan->stageTransitions()->count());

        // انتقال به همان مرحله‌ای که از قبل در آن است نباید ردیف تازه‌ای بسازد
        $plan->moveToStage($research);
        $this->assertSame(1, $plan->fresh()->stageTransitions()->count());
    }

    public function test_move_to_stage_records_actor_and_from_stage(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();
        $research = $this->stage('research');

        $plan->moveToStage($research, $user);

        $transition = $plan->stageTransitions()->first();
        $this->assertSame($this->stage('idea')->id, $transition->from_stage_id);
        $this->assertSame($research->id, $transition->to_stage_id);
        $this->assertSame($user->id, $transition->changed_by);
    }

    public function test_reaching_ai_draft_materializes_an_article_with_no_prior_contentable(): void
    {
        $tag = Tag::create(['name' => 'BJJ']);
        $plan = $this->makePlan(['content_type' => 'Article']);
        $plan->tags()->attach($tag);

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->refresh();

        $this->assertSame('Article', $plan->contentable_type);
        $this->assertNotNull($plan->contentable_id);

        $article = $plan->contentable;
        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame('How to escape side control', $article->title);
        $this->assertSame('draft', $article->status);
        $this->assertSame('en', $article->locale);
        $this->assertSame('Technique', $article->category);
        $this->assertTrue($article->tags->contains($tag));

        // رابطه‌ی معکوس هم باید کار کند
        $this->assertTrue($article->contentPlan->is($plan));
    }

    public function test_reaching_ai_draft_materializes_a_page_when_content_type_is_page(): void
    {
        $plan = $this->makePlan(['content_type' => 'Page', 'title' => 'Refund Policy']);

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->refresh();

        $this->assertSame('Page', $plan->contentable_type);
        $page = $plan->contentable;
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('Refund Policy', $page->title);
    }

    public function test_materialize_content_defaults_to_article_when_content_type_is_unset(): void
    {
        $plan = $this->makePlan(['content_type' => null]);

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        $this->assertSame('Article', $plan->fresh()->contentable_type);
    }

    public function test_materialize_content_deduplicates_slug_collisions_scoped_by_locale(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'Dup', 'slug' => 'how-to-escape-side-control',
            'body' => '', 'author_name' => 'Ehsan', 'status' => 'draft',
        ]);

        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        $this->assertSame('how-to-escape-side-control-2', $plan->fresh()->contentable->slug);
    }

    public function test_materialize_content_is_idempotent_once_a_contentable_already_exists(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $firstContentableId = $plan->fresh()->contentable_id;

        // یک انتقال دیگر (به عقب و دوباره به ai_draft) نباید رکورد دومی بسازد
        $plan->moveToStage($this->stage('research'));
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));

        $this->assertSame($firstContentableId, $plan->fresh()->contentable_id);
        $this->assertSame(1, Article::count());
    }

    public function test_effective_publish_date_prefers_the_contentables_published_at_over_planned_publish_at(): void
    {
        $plan = $this->makePlan(['planned_publish_at' => now()->addDays(3)]);
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->refresh();

        $article = $plan->contentable;
        $publishAt = now()->addDay();
        $article->update(['status' => 'scheduled', 'published_at' => $publishAt]);

        $this->assertEquals($publishAt->timestamp, $plan->fresh()->effectivePublishDate()->timestamp);
    }

    public function test_effective_publish_date_falls_back_to_planned_publish_at_without_a_contentable(): void
    {
        $publishAt = now()->addWeek();
        $plan = $this->makePlan(['planned_publish_at' => $publishAt]);

        $this->assertEquals($publishAt->timestamp, $plan->effectivePublishDate()->timestamp);
    }

    public function test_content_tasks_can_be_attached_to_a_plan_with_status_due_date_assignee_and_notes(): void
    {
        $plan = $this->makePlan();
        $user = User::factory()->create();

        $task = ContentTask::create([
            'content_plan_id' => $plan->id,
            'title' => 'Write introduction',
            'status' => ContentTask::STATUS_PENDING,
            'due_at' => now()->addDays(2),
            'assigned_to' => $user->id,
            'notes' => 'Keep it under 100 words.',
        ]);

        $this->assertTrue($plan->tasks->contains($task));
        $this->assertTrue($task->assignee->is($user));
    }

    public function test_publish_due_articles_advances_a_linked_content_plan_to_the_published_stage(): void
    {
        $plan = $this->makePlan();
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_AI_DRAFT));
        $plan->moveToStage($this->stage(WorkflowStage::STAGE_SCHEDULED));

        $article = $plan->fresh()->contentable;
        $article->update(['status' => 'scheduled', 'published_at' => now()->subMinute()]);

        $this->artisan(PublishDueArticles::class)->assertExitCode(0);

        $this->assertSame('published', $article->fresh()->status);
        $this->assertSame($this->stage(WorkflowStage::STAGE_PUBLISHED)->id, $plan->fresh()->workflow_stage_id);
    }

    public function test_publish_due_articles_is_unaffected_by_articles_with_no_linked_content_plan(): void
    {
        $article = Article::create([
            'locale' => 'en', 'title' => 'Standalone', 'slug' => 'standalone-'.uniqid(),
            'body' => '', 'author_name' => 'Ehsan', 'status' => 'scheduled',
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan(PublishDueArticles::class)->assertExitCode(0);

        $this->assertSame('published', $article->fresh()->status);
    }

    public function test_content_plan_changes_are_recorded_in_the_activity_log(): void
    {
        $plan = $this->makePlan();
        $plan->update(['title' => 'Renamed idea']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => 'ContentPlan',
            'subject_id' => $plan->id,
            'log_name' => 'content_plan',
        ]);
    }

    public function test_deleting_a_workflow_stage_in_use_is_prevented_at_the_database_level(): void
    {
        $plan = $this->makePlan();
        $ideaStage = $this->stage(WorkflowStage::STAGE_IDEA);

        $this->expectException(QueryException::class);
        $ideaStage->delete();

        $this->assertDatabaseHas('content_plans', ['id' => $plan->id]);
    }
}
