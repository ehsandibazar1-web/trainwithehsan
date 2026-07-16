<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyApproachingDeadlines;
use App\Models\ContentPlan;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Notifications\DeadlineApproaching;
use App\Notifications\PublishingCompleted;
use App\Notifications\ReviewRequested;
use App\Notifications\WorkflowStageChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EditorialNotificationsTest extends TestCase
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
            'workflow_stage_id' => $this->stage(WorkflowStage::STAGE_IDEA)->id,
            'priority' => ContentPlan::PRIORITY_MEDIUM,
        ], $overrides));
    }

    public function test_moving_stage_notifies_the_assignee_of_the_change(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id]);

        $plan->moveToStage($this->stage('research'));

        Notification::assertSentTo($assignee, WorkflowStageChanged::class);
    }

    public function test_moving_stage_falls_back_to_the_author_when_no_assignee_is_set(): void
    {
        Notification::fake();
        $author = User::factory()->create();
        $plan = $this->makePlan(['author_id' => $author->id]);

        $plan->moveToStage($this->stage('research'));

        Notification::assertSentTo($author, WorkflowStageChanged::class);
    }

    public function test_no_notification_is_sent_when_the_plan_has_neither_assignee_nor_author(): void
    {
        Notification::fake();
        $plan = $this->makePlan();

        $plan->moveToStage($this->stage('research'));

        Notification::assertNothingSent();
    }

    public function test_entering_human_review_or_seo_review_also_fires_review_requested(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id]);

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_HUMAN_REVIEW));

        Notification::assertSentTo($assignee, WorkflowStageChanged::class);
        Notification::assertSentTo($assignee, ReviewRequested::class);
        Notification::assertNotSentTo($assignee, PublishingCompleted::class);
    }

    public function test_entering_published_fires_publishing_completed(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id]);

        $plan->moveToStage($this->stage(WorkflowStage::STAGE_PUBLISHED));

        Notification::assertSentTo($assignee, PublishingCompleted::class);
        Notification::assertNotSentTo($assignee, ReviewRequested::class);
    }

    public function test_a_user_can_opt_out_of_a_specific_event_and_channel(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $assignee->id,
            'event_key' => WorkflowStageChanged::EVENT_KEY,
            'channel' => 'database',
            'enabled' => false,
        ]);
        $plan = $this->makePlan(['assigned_to' => $assignee->id]);

        $plan->moveToStage($this->stage('research'));

        Notification::assertNotSentTo($assignee, WorkflowStageChanged::class);
    }

    public function test_stage_change_notification_stores_a_filament_compatible_database_message(): void
    {
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id]);

        $plan->moveToStage($this->stage('research'));

        $record = $assignee->notifications()->first();
        $this->assertNotNull($record);
        $this->assertArrayHasKey('title', $record->data);
        $this->assertArrayHasKey('body', $record->data);
        $this->assertStringContainsString('Idea', $record->data['body']);
        $this->assertStringContainsString('Research', $record->data['body']);
    }

    public function test_notify_deadlines_command_notifies_plans_due_within_24_hours_once(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id, 'due_at' => now()->addHours(5)]);

        $this->artisan(NotifyApproachingDeadlines::class)->assertExitCode(0);

        Notification::assertSentTo($assignee, DeadlineApproaching::class);
        $this->assertNotNull($plan->fresh()->deadline_notified_at);

        // اجرای دوباره نباید دوباره اعلان بفرستد
        Notification::fake();
        $this->artisan(NotifyApproachingDeadlines::class)->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_notify_deadlines_command_ignores_plans_outside_the_24_hour_window(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $this->makePlan(['assigned_to' => $assignee->id, 'due_at' => now()->addDays(3)]);
        $this->makePlan(['assigned_to' => $assignee->id, 'due_at' => now()->subDay(), 'title' => 'Overdue']);

        $this->artisan(NotifyApproachingDeadlines::class)->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_notify_deadlines_command_skips_plans_already_published_or_archived(): void
    {
        Notification::fake();
        $assignee = User::factory()->create();
        $plan = $this->makePlan(['assigned_to' => $assignee->id, 'due_at' => now()->addHours(2)]);
        $plan->update(['workflow_stage_id' => $this->stage(WorkflowStage::STAGE_PUBLISHED)->id]);

        $this->artisan(NotifyApproachingDeadlines::class)->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_changing_due_at_resets_deadline_notified_at_so_a_rescheduled_deadline_warns_again(): void
    {
        $plan = $this->makePlan(['due_at' => now()->addHours(5), 'deadline_notified_at' => now()]);

        $plan->update(['due_at' => now()->addHours(10)]);

        $this->assertNull($plan->fresh()->deadline_notified_at);
    }
}
