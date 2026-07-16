<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\ContentPlans\ContentPlanResource;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Article;
use App\Models\ContentPlan;
use App\Models\ContentPlanStageTransition;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
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

    // پارامتر آدرس ?view=calendar اجازه می‌دهد لینک‌های قدیمی (مثلاً EditorialCalendar سابق) دقیقاً
    // به همان تب Calendar برسند، نه به‌طور پیش‌فرض به Kanban
    #[Url(as: 'view')]
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

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
    }

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

    // نمای Table از همین filteredPlans استفاده می‌کند (بدون کوئری دوم) — فقط ترتیب متفاوت
    public function getTablePlansProperty(): Collection
    {
        return $this->filteredPlans->sortByDesc('updated_at')->values();
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

    // ============ یکپارچگی با AI Studio ============
    // هرگز مستقیماً چیزی تولید نمی‌کند (طبق قاعده‌ی ثابت‌شده‌ی «تولید همیشه با کلیک صریح ادمین
    // داخل AiAssistantPanel است») — این متدها فقط رکورد را (در صورت نیاز) مادیت می‌بخشند و
    // ادمین را به همان صفحه‌ی ویرایش/دستیاری می‌برند که Generate Draft/FAQ/SEO/Translate/Optimize
    // از قبل آنجا وجود دارند، بدون هیچ منطق هوش مصنوعی تازه‌ای.

    /**
     * «Generate Draft» روی یک کارت بدون contentable — رکورد Article/Page را می‌سازد (اگر نبود)،
     * مرحله را به AI Draft می‌برد، و ادمین را مستقیماً به صفحه‌ی ویرایش (با نوار کناری دستیار
     * هوش مصنوعی از قبل تعبیه‌شده) هدایت می‌کند.
     */
    public function generateDraft(int $planId): void
    {
        $plan = ContentPlan::find($planId);

        if (! $plan) {
            return;
        }

        if (! $plan->contentable_id) {
            $aiDraftStage = WorkflowStage::findBySlug(WorkflowStage::STAGE_AI_DRAFT);

            if ($aiDraftStage) {
                $plan->moveToStage($aiDraftStage, Auth::user());
            } else {
                $plan->materializeContent();
            }

            $plan->refresh();
        }

        if (! $plan->contentable_id) {
            Notification::make()->danger()->title('Could not create a draft for this idea')->send();

            return;
        }

        $this->redirect($this->contentEditUrl($plan->contentable_type, $plan->contentable_id));
    }

    // لینک مستقیم به دستیار هوش مصنوعیِ همین رکورد (صفحه‌ی مستقل AiContentAssistant، همان
    // چیزی که قبلاً به‌عنوان "fallback/deep link" مستقل نگه داشته شده) — null اگر هنوز
    // contentable ندارد (که یعنی دکمه‌ی Generate Draft باید نشان داده شود، نه این)
    public function aiAssistantUrlFor(ContentPlan $plan): ?string
    {
        if (! $plan->contentable_id || ! $plan->contentable_type) {
            return null;
        }

        return $plan->contentable_type === 'Page'
            ? AiContentAssistant::getUrl(['page' => $plan->contentable_id])
            : AiContentAssistant::getUrl(['article' => $plan->contentable_id]);
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

    // ============ Calendar — جانشین EditorialCalendar، همان منطق ماه/هفته منتقل شده ============
    // (نه بازنویسی‌شده) و گسترش‌یافته برای Page + پین‌های planned/deadline کارت‌های برنامه‌ریز

    public function previousMonth(): void
    {
        $c = Carbon::create($this->year, $this->month, 1)->subMonth();
        $this->year = $c->year;
        $this->month = $c->month;
    }

    public function nextMonth(): void
    {
        $c = Carbon::create($this->year, $this->month, 1)->addMonth();
        $this->year = $c->year;
        $this->month = $c->month;
    }

    public function goToday(): void
    {
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
    }

    public function getMonthLabelProperty(): string
    {
        return Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    /** @return array<int, array<int, array<string, mixed>>> */
    public function getCalendarWeeksProperty(): array
    {
        $firstOfMonth = Carbon::create($this->year, $this->month, 1);
        $start = $firstOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $firstOfMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $range = [$start->copy()->startOfDay(), $end->copy()->endOfDay()];

        $articles = Article::whereNotNull('published_at')
            ->whereBetween('published_at', $range)
            ->orderBy('published_at')
            ->get()
            ->groupBy(fn (Article $a) => $a->published_at->format('Y-m-d'));

        $pages = PageModel::whereNotNull('published_at')
            ->whereBetween('published_at', $range)
            ->orderBy('published_at')
            ->get()
            ->groupBy(fn (PageModel $p) => $p->published_at->format('Y-m-d'));

        // فقط ایده‌هایی که هنوز contentable ندارند — بعد از مادیت‌بخشی، تاریخ از خودِ Article/Page خوانده می‌شود
        $planned = ContentPlan::whereNull('contentable_id')
            ->whereNotNull('planned_publish_at')
            ->whereBetween('planned_publish_at', $range)
            ->orderBy('planned_publish_at')
            ->get()
            ->groupBy(fn (ContentPlan $p) => $p->planned_publish_at->format('Y-m-d'));

        $excludedStageIds = array_filter([
            WorkflowStage::findBySlug(WorkflowStage::STAGE_PUBLISHED)?->id,
            WorkflowStage::findBySlug(WorkflowStage::STAGE_ARCHIVED)?->id,
        ]);

        $deadlines = ContentPlan::whereNotNull('due_at')
            ->whereBetween('due_at', $range)
            ->when($excludedStageIds !== [], fn ($q) => $q->whereNotIn('workflow_stage_id', $excludedStageIds))
            ->orderBy('due_at')
            ->get()
            ->groupBy(fn (ContentPlan $p) => $p->due_at->format('Y-m-d'));

        $weeks = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $key = $cursor->format('Y-m-d');
                $week[] = [
                    'date' => $cursor->copy(),
                    'inMonth' => $cursor->month === $this->month,
                    'isToday' => $cursor->isToday(),
                    'articles' => $articles->get($key, collect()),
                    'pages' => $pages->get($key, collect()),
                    'planned' => $planned->get($key, collect()),
                    'deadlines' => $deadlines->get($key, collect()),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    public function contentEditUrl(string $type, int $id): string
    {
        return $type === 'Page'
            ? PageResource::getUrl('edit', ['record' => $id])
            : ArticleResource::getUrl('edit', ['record' => $id]);
    }

    /**
     * فراخوانی از سمت جاوااسکریپت هنگام رهاکردن یک چیپ روی روز جدید — فقط تاریخ عوض می‌شود
     * (ساعت قبلی حفظ می‌شود)، دقیقاً همان رفتار EditorialCalendar::moveArticle() قبلی.
     */
    public function rescheduleItem(string $kind, ?string $type, int $id, string $newDate): void
    {
        match ($kind) {
            'content' => $this->rescheduleContent($type ?? 'Article', $id, $newDate),
            'planned' => $this->reschedulePlanned($id, $newDate),
            'deadline' => $this->rescheduleDeadline($id, $newDate),
            default => null,
        };
    }

    private function rescheduleContent(string $type, int $id, string $newDate): void
    {
        $model = $type === 'Page' ? PageModel::find($id) : Article::find($id);

        if (! $model || ! $model->published_at) {
            return;
        }

        $time = $model->published_at->format('H:i:s');
        $model->update(['published_at' => Carbon::parse($newDate.' '.$time)]);

        Notification::make()->success()->title('Moved to '.$newDate)->send();
    }

    private function reschedulePlanned(int $id, string $newDate): void
    {
        $plan = ContentPlan::find($id);

        if (! $plan) {
            return;
        }

        $time = $plan->planned_publish_at?->format('H:i:s') ?? '09:00:00';
        $plan->update(['planned_publish_at' => Carbon::parse($newDate.' '.$time)]);

        Notification::make()->success()->title('Planned date moved to '.$newDate)->send();
    }

    private function rescheduleDeadline(int $id, string $newDate): void
    {
        $plan = ContentPlan::find($id);

        if (! $plan) {
            return;
        }

        $time = $plan->due_at?->format('H:i:s') ?? '17:00:00';
        $plan->update(['due_at' => Carbon::parse($newDate.' '.$time)]);

        Notification::make()->success()->title('Deadline moved to '.$newDate)->send();
    }

    // ============ Dashboard — همه از content_plans/content_plan_stage_transitions موجود، بدون جدول تازه ============

    /** @return array<string, int|float|null> */
    public function getDashboardStatsProperty(): array
    {
        $counts = ContentPlan::query()
            ->selectRaw('workflow_stage_id, count(*) as aggregate')
            ->groupBy('workflow_stage_id')
            ->pluck('aggregate', 'workflow_stage_id');

        $stageId = fn (string $slug): ?int => WorkflowStage::findBySlug($slug)?->id;
        $reviewStageIds = array_filter([$stageId(WorkflowStage::STAGE_HUMAN_REVIEW), $stageId(WorkflowStage::STAGE_SEO_REVIEW)]);

        return [
            'ideas' => (int) $counts->get($stageId(WorkflowStage::STAGE_IDEA), 0),
            'drafts' => (int) $counts->get($stageId(WorkflowStage::STAGE_AI_DRAFT), 0),
            'reviews' => (int) collect($reviewStageIds)->sum(fn ($id) => $counts->get($id, 0)),
            'scheduled' => (int) $counts->get($stageId(WorkflowStage::STAGE_SCHEDULED), 0),
            'published' => (int) $counts->get($stageId(WorkflowStage::STAGE_PUBLISHED), 0),
            'total' => (int) $counts->sum(),
            'avg_publishing_days' => $this->averagePublishingTimeDays(),
            'avg_review_days' => $this->averageStageDurationDays([WorkflowStage::STAGE_HUMAN_REVIEW, WorkflowStage::STAGE_SEO_REVIEW]),
        ];
    }

    /** @return array<int, array{label: string, count: int}> */
    public function getProductionPerMonthProperty(): array
    {
        $publishedStageId = WorkflowStage::findBySlug(WorkflowStage::STAGE_PUBLISHED)?->id;

        if (! $publishedStageId) {
            return [];
        }

        $months = 6;
        $since = now()->subMonths($months - 1)->startOfMonth();

        $byMonth = ContentPlanStageTransition::where('to_stage_id', $publishedStageId)
            ->where('created_at', '>=', $since)
            ->get()
            ->groupBy(fn (ContentPlanStageTransition $t) => $t->created_at->format('Y-m'));

        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $moment = now()->subMonths($i);
            $result[] = [
                'label' => $moment->translatedFormat('M Y'),
                'count' => $byMonth->get($moment->format('Y-m'), collect())->count(),
            ];
        }

        return $result;
    }

    // میانگین زمان از ساخت کارت تا رسیدن به Published (روز) — فقط اولین ورود به این مرحله به‌ازای هر کارت
    private function averagePublishingTimeDays(): ?float
    {
        $publishedStageId = WorkflowStage::findBySlug(WorkflowStage::STAGE_PUBLISHED)?->id;

        if (! $publishedStageId) {
            return null;
        }

        $durations = ContentPlanStageTransition::where('to_stage_id', $publishedStageId)
            ->with('contentPlan')
            ->orderBy('created_at')
            ->get()
            ->unique('content_plan_id')
            ->filter(fn (ContentPlanStageTransition $t) => $t->contentPlan !== null)
            ->map(fn (ContentPlanStageTransition $t) => $t->contentPlan->created_at->diffInHours($t->created_at) / 24);

        return $durations->isNotEmpty() ? round($durations->avg(), 1) : null;
    }

    // میانگین زمان توقف در مرحله(های) داده‌شده (روز) — فقط بازدیدهایی که به مرحله‌ی بعدی رسیده‌اند
    // (نه بازدیدهای هنوز جاری) در میانگین حساب می‌شوند
    private function averageStageDurationDays(array $stageSlugs): ?float
    {
        $stageIds = WorkflowStage::whereIn('slug', $stageSlugs)->pluck('id')->all();

        if ($stageIds === []) {
            return null;
        }

        $durations = collect();

        ContentPlanStageTransition::orderBy('content_plan_id')->orderBy('created_at')->orderBy('id')
            ->get()
            ->groupBy('content_plan_id')
            ->each(function (Collection|SupportCollection $transitions) use ($stageIds, $durations) {
                $ordered = $transitions->values();

                foreach ($ordered as $i => $transition) {
                    if (! in_array($transition->to_stage_id, $stageIds, true)) {
                        continue;
                    }

                    $next = $ordered->get($i + 1);

                    if ($next) {
                        $durations->push($transition->created_at->diffInHours($next->created_at) / 24);
                    }
                }
            });

        return $durations->isNotEmpty() ? round($durations->avg(), 1) : null;
    }
}
