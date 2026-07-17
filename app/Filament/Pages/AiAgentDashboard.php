<?php

namespace App\Filament\Pages;

use App\Jobs\RunAgentAudit;
use App\Models\AiAuditRun;
use App\Models\AiRecommendation;
use App\Services\AiAgent\AgentFixService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * داشبورد AI Agent — پروآکتیو یعنی همین: به‌جای منتظر ماندن برای درخواست ادمین، همه‌ی محتوای
 * سایت به‌طور خودکار (هفتگی، agent:audit) یا دستی («Run Audit Now») گشته می‌شود و فرصت‌های بهبود
 * به‌عنوان ردیف‌های App\Models\AiRecommendation ذخیره می‌شوند. همان الگوی «Page سفارشی +
 * سایدبار دسته‌ها + جدول یافته‌ها» که SeoCenter/InternalLinkingCenter تثبیت کرده‌اند — نگاه کنید
 * به CLAUDE.md. تمام تشخیص در App\Services\AiAgent\AgentAuditService است، تمام رفع/تایید/رد در
 * App\Services\AiAgent\AgentFixService — این کلاس فقط حالت Livewire و صداکردنِ آن دو را دارد.
 */
class AiAgentDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'AI Agent';

    protected static ?string $title = 'AI Agent';

    protected string $view = 'filament.pages.ai-agent-dashboard';

    // ترتیب نمایش در سایدبار — شانزده دسته‌ی درخواست‌شده، نگاه کنید به AgentAuditService::run()
    public const CATEGORIES = [
        'content_refresh' => 'Content Refresh',
        'missing_internal_links' => 'Missing Internal Links',
        'missing_faq' => 'Missing FAQ Opportunities',
        'weak_intro' => 'Weak Introductions',
        'weak_conclusion' => 'Weak Conclusions',
        'missing_cta' => 'Missing CTA',
        'missing_alt' => 'Missing ALT Text',
        'broken_links' => 'Broken Links',
        'thin_content' => 'Thin Content',
        'duplicate_topics' => 'Duplicate Topics',
        'content_cannibalization' => 'Content Cannibalization',
        'missing_schema' => 'Missing Schema',
        'poor_seo' => 'Pages With Poor SEO',
        'image_optimization' => 'Images Requiring Optimization',
        'needs_translation' => 'Pages That Should Be Translated',
        'orphan_pages' => 'Orphan Pages',
    ];

    public string $activeCategory = 'content_refresh';

    public string $statusFilter = 'pending';

    public string $localeFilter = 'all';

    public string $search = '';

    public function runAuditNow(): void
    {
        RunAgentAudit::dispatch();

        Notification::make()
            ->success()
            ->title('Audit queued')
            ->body('This runs in the background — a queue worker must be running (php artisan queue:work) for it to complete. This page refreshes automatically while it runs.')
            ->persistent()
            ->send();
    }

    public function getLatestRunProperty(): ?AiAuditRun
    {
        return AiAuditRun::latest('id')->first();
    }

    public function getIsAuditRunningProperty(): bool
    {
        return $this->latestRun?->status === 'running';
    }

    public function getIsPollingProperty(): bool
    {
        return $this->isAuditRunning
            || AiRecommendation::whereHas('generation', fn ($q) => $q->whereIn('status', ['queued', 'processing']))->exists();
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
        $this->search = '';
    }

    public function getCategoryCountsProperty(): array
    {
        $counts = AiRecommendation::pending()
            ->selectRaw('category, count(*) as c')
            ->groupBy('category')
            ->pluck('c', 'category');

        $result = [];
        foreach (self::CATEGORIES as $key => $label) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }

    public function getTotalPendingProperty(): int
    {
        return array_sum($this->categoryCounts);
    }

    public function getTotalAppliedProperty(): int
    {
        return AiRecommendation::where('status', 'applied')->count();
    }

    public function getTotalRejectedProperty(): int
    {
        return AiRecommendation::where('status', 'rejected')->count();
    }

    public function getFindingsProperty(): Collection
    {
        return AiRecommendation::category($this->activeCategory)
            ->when($this->statusFilter !== 'all', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->localeFilter !== 'all', fn ($q) => $q->where('locale', $this->localeFilter))
            ->when($this->search !== '', function ($q) {
                $needle = '%'.$this->search.'%';
                $q->where(fn ($qq) => $qq->where('title', 'like', $needle)->orWhere('detail', 'like', $needle));
            })
            ->with('generation')
            ->latest('id')
            ->get();
    }

    public function queueFix(int $id): void
    {
        $recommendation = AiRecommendation::find($id);

        if (! $recommendation) {
            return;
        }

        $queued = app(AgentFixService::class)->queueFix($recommendation);

        if ($queued) {
            Notification::make()
                ->success()
                ->title('Fix queued')
                ->body('This runs in the background — a queue worker must be running (php artisan queue:work) for it to complete.')
                ->persistent()
                ->send();
        } else {
            Notification::make()->danger()->title('No automatic fix is available, or the content no longer exists')->send();
        }
    }

    public function approveFix(int $id): void
    {
        $recommendation = AiRecommendation::find($id);

        if (! $recommendation) {
            return;
        }

        $applied = app(AgentFixService::class)->approveFix($recommendation);

        if ($applied) {
            Notification::make()->success()->title('Fix applied')->send();
        } else {
            Notification::make()->danger()->title("The fix isn't ready yet — wait for the queued generation to complete")->send();
        }
    }

    public function rejectFix(int $id): void
    {
        $recommendation = AiRecommendation::find($id);

        if (! $recommendation) {
            return;
        }

        app(AgentFixService::class)->rejectFix($recommendation);

        Notification::make()->success()->title('Recommendation dismissed')->send();
    }
}
