<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ContentPlans\ContentPlanResource;
use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\Page as PageModel;
use App\Models\Tag;
use App\Models\User;
use App\Models\WorkflowStage;
use App\Services\AiAssistant\ContentReviewService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * مرکز اصلیِ Editorial Workflow — سه نما (Kanban/Calendar/Table) + داشبورد آمار روی یک صفحه،
 * با یک نوار فیلتر/جستجوی مشترک بین همه‌ی نماها، دقیقاً طبق همان الگوی «یک صفحه، چند تب» که
 * SeoCenter/InternalLinkingCenter/AiContentAssistant قبلاً استفاده کرده‌اند — نه چهار آیتم
 * جدا در نویگیشن. جزئیات کامل در CLAUDE.md.
 */
class ContentPlanner extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static string|UnitEnum|null $navigationGroup = 'Content Planner';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Planner';

    protected static ?string $title = 'Content Planner';

    protected string $view = 'filament.pages.content-planner';

    public const PUBLICATION_STATUSES = [
        'none' => 'No content yet',
        'draft' => 'Draft',
        'scheduled' => 'Scheduled',
        'published' => 'Published',
    ];

    public string $activeView = 'kanban'; // kanban | calendar | table | dashboard

    public string $filterStage = 'all';

    public string $filterLocale = 'all';

    public string $filterAuthor = 'all';

    public string $filterCategory = 'all';

    public string $filterTag = 'all';

    public string $filterPriority = 'all';

    public string $filterPublicationStatus = 'all';

    public string $search = '';

    /** @var array<int, int> */
    public array $selectedPlanIds = [];

    public ?int $bulkStageId = null;

    public string $bulkPriority = ContentPlan::PRIORITY_MEDIUM;

    public function setView(string $view): void
    {
        $this->activeView = $view;
    }

    public function resetFilters(): void
    {
        $this->filterStage = 'all';
        $this->filterLocale = 'all';
        $this->filterAuthor = 'all';
        $this->filterCategory = 'all';
        $this->filterTag = 'all';
        $this->filterPriority = 'all';
        $this->filterPublicationStatus = 'all';
        $this->search = '';
    }

    // ============ داده‌ی مشترک بین هر سه نما ============

    public function getStagesProperty(): Collection
    {
        return WorkflowStage::orderBy('sort_order')->get();
    }

    public function getFilteredPlansProperty(): Collection
    {
        return $this->baseQuery()->get();
    }

    public function getPlansByStageProperty(): Collection
    {
        return $this->filteredPlans->groupBy('workflow_stage_id');
    }

    private function baseQuery(): Builder
    {
        return ContentPlan::query()
            ->with(['workflowStage', 'author', 'assignee', 'tags', 'contentable'])
            ->when($this->filterStage !== 'all', fn ($q) => $q->where('workflow_stage_id', $this->filterStage))
            ->when($this->filterLocale !== 'all', fn ($q) => $q->where('locale', $this->filterLocale))
            ->when($this->filterAuthor !== 'all', fn ($q) => $q->where('author_id', $this->filterAuthor))
            ->when($this->filterCategory !== 'all', fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->filterTag !== 'all', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $this->filterTag)))
            ->when($this->filterPriority !== 'all', fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterPublicationStatus !== 'all', function (Builder $q) {
                if ($this->filterPublicationStatus === 'none') {
                    $q->whereNull('contentable_id');
                } else {
                    $q->whereHasMorph('contentable', [Article::class, PageModel::class], fn ($sub) => $sub->where('status', $this->filterPublicationStatus));
                }
            })
            ->when($this->search !== '', fn (Builder $q) => $q->where('title', 'like', '%'.$this->search.'%'));
    }

    // ============ گزینه‌های نوار فیلتر ============

    public function getFilterAuthorsProperty(): SupportCollection
    {
        return User::orderBy('name')->pluck('name', 'id');
    }

    public function getFilterCategoriesProperty(): SupportCollection
    {
        return ContentPlan::whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category');
    }

    public function getFilterTagsProperty(): SupportCollection
    {
        return Tag::orderBy('name')->pluck('name', 'id');
    }

    // ============ امتیازهای کارت — بازاستفاده از ContentReviewService، بدون منطق تازه ============

    public function scoreCardFor(ContentPlan $plan): array
    {
        if (! $plan->contentable) {
            return [];
        }

        return app(ContentReviewService::class)->scoreCard($plan->contentable);
    }

    public function editUrlFor(ContentPlan $plan): string
    {
        return ContentPlanResource::getUrl('edit', ['record' => $plan->id]);
    }

    // ============ Kanban: درگ‌اند‌دراپ ============

    public function moveCard(int $planId, int $stageId): void
    {
        $plan = ContentPlan::find($planId);
        $stage = WorkflowStage::find($stageId);

        if (! $plan || ! $stage) {
            return;
        }

        $plan->moveToStage($stage, Auth::user());

        Notification::make()->success()->title("Moved to {$stage->label}")->send();
    }

    // ============ Kanban: اقدامات گروهی ============

    public function bulkMoveStage(): void
    {
        $stage = WorkflowStage::find($this->bulkStageId);

        if (! $stage || empty($this->selectedPlanIds)) {
            return;
        }

        $actor = Auth::user();
        ContentPlan::whereIn('id', $this->selectedPlanIds)->get()
            ->each(fn (ContentPlan $plan) => $plan->moveToStage($stage, $actor));

        $count = count($this->selectedPlanIds);
        $this->selectedPlanIds = [];

        Notification::make()->success()->title("Moved {$count} card(s) to {$stage->label}")->send();
    }

    public function bulkSetPriority(): void
    {
        if (empty($this->selectedPlanIds)) {
            return;
        }

        $count = ContentPlan::whereIn('id', $this->selectedPlanIds)->update(['priority' => $this->bulkPriority]);
        $this->selectedPlanIds = [];

        Notification::make()->success()->title("Updated priority for {$count} card(s)")->send();
    }

    public function bulkDelete(): void
    {
        if (empty($this->selectedPlanIds)) {
            return;
        }

        $count = ContentPlan::whereIn('id', $this->selectedPlanIds)->delete();
        $this->selectedPlanIds = [];

        Notification::make()->success()->title("Deleted {$count} card(s)")->send();
    }
}
