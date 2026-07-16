<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Pages\PageResource;
use App\Jobs\GenerateInternalLinkSuggestions;
use App\Models\Article;
use App\Models\InternalLinkSuggestion;
use App\Models\Page as PageModel;
use App\Services\InternalLinking\LinkGraphService;
use App\Services\Seo\SeoAuditService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * داشبورد سلامتِ لینک‌سازی داخلی — دسته‌های orphan/broken را از SeoAuditService بازاستفاده
 * می‌کند (بدون تکرار منطق)، و قابلیت‌های تازه (شمارش ورودی/خروجی، ضعیف/بیش‌ازحد، پیشنهاد لینک،
 * نقشه‌ی گراف) را روی LinkGraphService/SuggestionEngine می‌سازد. جزئیات کامل در CLAUDE.md،
 * بخش «Internal Linking Center».
 */
class InternalLinkingCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    protected static ?string $navigationLabel = 'Internal Linking';

    protected static ?string $title = 'Internal Linking Center';

    protected string $view = 'filament.pages.internal-linking-center';

    public const CATEGORIES = [
        'orphan_articles' => 'Orphan Articles',
        'orphan_pages' => 'Orphan Pages',
        'no_inbound_links' => 'No Inbound Links',
        'no_outbound_links' => 'No Outbound Links',
        'weak_internal_linking' => 'Weak Internal Linking',
        'excessive_internal_links' => 'Excessive Internal Links',
        'broken_internal_links' => 'Broken Internal Links',
        'broken_external_links' => 'Broken External Links',
        'redirect_chains' => 'Redirect Chains',
    ];

    public string $activeTab = 'dashboard'; // dashboard | suggestions | graph

    public string $activeCategory = 'orphan_articles';

    public string $localeFilter = 'all';

    public string $typeFilter = 'all';

    public string $search = '';

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $findings = [];

    /** @var array<int, array<string, mixed>> */
    public array $externalLinkFindings = [];

    public bool $hasScannedExternalLinks = false;

    public string $suggestionLocaleFilter = 'all';

    public string $suggestionMinConfidence = '0';

    /** @var array<int, int> */
    public array $selectedSuggestionIds = [];

    public string $graphLocaleFilter = 'all';

    public string $graphCategoryFilter = 'all';

    public string $graphTypeFilter = 'all';

    public function mount(): void
    {
        $this->runAudit();
    }

    public function runAudit(): void
    {
        $seoFindings = app(SeoAuditService::class)->run();
        $graphService = app(LinkGraphService::class);
        $nodes = $graphService->build()['nodes'];

        $orphans = collect($seoFindings['orphan_pages']);

        $this->findings = [
            'orphan_articles' => $orphans->where('type', 'Article')->values()->all(),
            'orphan_pages' => $orphans->where('type', 'Page')->values()->all(),
            'no_inbound_links' => $graphService->noInboundLinks($nodes),
            'no_outbound_links' => $graphService->noOutboundLinks($nodes),
            'weak_internal_linking' => $graphService->weakInternalLinking($nodes),
            'excessive_internal_links' => $graphService->excessiveInternalLinks($nodes),
            'broken_internal_links' => $seoFindings['broken_internal_links'],
            'broken_external_links' => $this->externalLinkFindings,
            'redirect_chains' => $graphService->redirectChains(),
        ];

        Notification::make()->success()->title('Internal linking audit refreshed')->send();
    }

    public function scanExternalLinks(): void
    {
        $this->externalLinkFindings = app(SeoAuditService::class)->checkExternalLinks();
        $this->hasScannedExternalLinks = true;
        $this->findings['broken_external_links'] = $this->externalLinkFindings;

        Notification::make()
            ->success()
            ->title(count($this->externalLinkFindings).' broken external link(s) found')
            ->send();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setCategory(string $category): void
    {
        $this->activeCategory = $category;
        $this->typeFilter = 'all';
        $this->search = '';
    }

    public function getCategoryCountsProperty(): array
    {
        $counts = [];
        foreach (self::CATEGORIES as $key => $label) {
            $counts[$key] = count($this->findings[$key] ?? []);
        }

        return $counts;
    }

    public function getTotalIssuesProperty(): int
    {
        return array_sum($this->categoryCounts);
    }

    public function getFilteredFindingsProperty(): Collection
    {
        return $this->applyFindingFilters(collect($this->findings[$this->activeCategory] ?? []));
    }

    private function applyFindingFilters(Collection $findings): Collection
    {
        return $findings
            ->when($this->localeFilter !== 'all', fn (Collection $c) => $c->filter(fn ($f) => $f['locale'] === $this->localeFilter))
            ->when($this->typeFilter !== 'all', fn (Collection $c) => $c->filter(fn ($f) => $f['type'] === $this->typeFilter))
            ->when($this->search !== '', function (Collection $c) {
                $needle = mb_strtolower($this->search);

                return $c->filter(fn ($f) => str_contains(mb_strtolower($f['title'].' '.$f['detail']), $needle));
            })
            ->values();
    }

    public function getAvailableTypesProperty(): array
    {
        return collect($this->findings[$this->activeCategory] ?? [])
            ->pluck('type')->unique()->sort()->values()->all();
    }

    // ============ پیشنهادهای لینک داخلی ============

    public function generateSuggestions(): void
    {
        dispatch(new GenerateInternalLinkSuggestions);

        Notification::make()
            ->success()
            ->title('Suggestion generation queued')
            ->body('This runs in the background — a queue worker must be running (php artisan queue:work) for it to complete. Refresh this page in a moment to see fresh suggestions.')
            ->persistent()
            ->send();
    }

    public function getPendingSuggestionsProperty(): Collection
    {
        return InternalLinkSuggestion::pending()
            ->when($this->suggestionLocaleFilter !== 'all', fn ($q) => $q->where('locale', $this->suggestionLocaleFilter))
            ->where('confidence_score', '>=', (int) $this->suggestionMinConfidence)
            ->orderByDesc('confidence_score')
            ->get()
            ->map(fn (InternalLinkSuggestion $s) => $this->presentSuggestion($s));
    }

    private function presentSuggestion(InternalLinkSuggestion $suggestion): array
    {
        $source = $this->findModel($suggestion->source_type, $suggestion->source_id);
        $target = $this->findModel($suggestion->target_type, $suggestion->target_id);

        return [
            'id' => $suggestion->id,
            'locale' => $suggestion->locale,
            'confidence' => $suggestion->confidence_score,
            'anchor' => $suggestion->recommended_anchor_text,
            'reason' => $suggestion->reason,
            'source_label' => $source ? $source->title.' ('.strtoupper($suggestion->locale).')' : "#{$suggestion->source_id} (deleted)",
            'target_label' => $target ? $target->title.' ('.strtoupper($suggestion->locale).')' : "#{$suggestion->target_id} (deleted)",
            'source_edit_url' => $source ? $this->editUrlFor($suggestion->source_type, $source) : null,
        ];
    }

    public function approveSuggestion(int $id): void
    {
        $suggestion = InternalLinkSuggestion::find($id);
        if (! $suggestion) {
            return;
        }

        $ok = $this->insertLinkForSuggestion($suggestion);

        $notification = Notification::make()->title($ok ? 'Link added' : 'Could not add link — source or target no longer exists');
        $ok ? $notification->success() : $notification->danger();
        $notification->send();
    }

    public function dismissSuggestion(int $id): void
    {
        InternalLinkSuggestion::whereKey($id)->update(['status' => 'dismissed']);
        Notification::make()->success()->title('Suggestion dismissed')->send();
    }

    public function approveSelected(): void
    {
        $count = InternalLinkSuggestion::whereIn('id', $this->selectedSuggestionIds)
            ->get()
            ->filter(fn (InternalLinkSuggestion $s) => $this->insertLinkForSuggestion($s))
            ->count();

        $this->selectedSuggestionIds = [];

        Notification::make()->success()->title($count.' link(s) added')->send();
    }

    public function dismissSelected(): void
    {
        $count = InternalLinkSuggestion::whereIn('id', $this->selectedSuggestionIds)->update(['status' => 'dismissed']);
        $this->selectedSuggestionIds = [];

        Notification::make()->success()->title($count.' suggestion(s) dismissed')->send();
    }

    /**
     * لینک واقعی را به انتهای بدنه‌ی مقاله/صفحه‌ی منبع اضافه می‌کند — عمدا در انتها (append)، نه
     * وسط محتوا، تا هرگز قالب‌بندی موجود مقاله را خراب نکند. بلوک اضافه‌شده با
     * data-internal-link-suggestion قابل‌شناسایی است و approve دوباره روی همون پیشنهاد، لینک
     * تکراری اضافه نمی‌کند.
     */
    private function insertLinkForSuggestion(InternalLinkSuggestion $suggestion): bool
    {
        $source = $this->findModel($suggestion->source_type, $suggestion->source_id);
        $target = $this->findModel($suggestion->target_type, $suggestion->target_id);

        if (! $source || ! $target) {
            $suggestion->update(['status' => 'dismissed']);

            return false;
        }

        $url = url($target->path());

        if (! str_contains((string) $source->body, 'href="'.$url.'"')) {
            $anchor = e($suggestion->recommended_anchor_text);
            $source->body = rtrim((string) $source->body)
                ."\n".'<p class="internal-link-suggestion" data-internal-link-suggestion="'.$suggestion->id.'"><a href="'.$url.'">'.$anchor.'</a></p>';
            $source->save();
        }

        $suggestion->update(['status' => 'approved', 'approved_at' => now()]);

        return true;
    }

    private function findModel(string $type, int $id): Article|PageModel|null
    {
        return $type === 'Article' ? Article::find($id) : PageModel::find($id);
    }

    private function editUrlFor(string $type, Article|PageModel $model): string
    {
        return $type === 'Article'
            ? ArticleResource::getUrl('edit', ['record' => $model->id])
            : PageResource::getUrl('edit', ['record' => $model->id]);
    }

    // ============ نقشه‌ی گراف ============

    public function getGraphCategoriesProperty(): array
    {
        return Article::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category')->all();
    }

    /**
     * گره‌ها/یال‌های فیلترشده + مختصات از‌پیش‌محاسبه‌شده برای چیدمان دایره‌ای — بدون کتابخانه‌ی
     * جاوااسکریپتی تازه (چیدمان قطعی/deterministic سمت سرور، نه فیزیک سمت کلاینت).
     */
    public function getGraphDataProperty(): array
    {
        $graph = app(LinkGraphService::class)->build();

        $nodes = $graph['nodes']
            ->when($this->graphLocaleFilter !== 'all', fn ($c) => $c->where('locale', $this->graphLocaleFilter))
            ->when($this->graphTypeFilter !== 'all', fn ($c) => $c->where('model', $this->graphTypeFilter))
            ->when($this->graphCategoryFilter !== 'all', fn ($c) => $c->where('category', $this->graphCategoryFilter));

        $keys = $nodes->keys()->values();
        $count = max($keys->count(), 1);
        $radius = 260;
        $center = 300;

        $positioned = [];
        foreach ($keys as $i => $key) {
            $angle = (2 * M_PI * $i) / $count;
            $positioned[$key] = array_merge($nodes[$key], [
                'x' => round($center + $radius * cos($angle), 1),
                'y' => round($center + $radius * sin($angle), 1),
            ]);
        }

        $edges = $graph['edges']->filter(fn ($e) => isset($positioned[$e['from']]) && isset($positioned[$e['to']]))->values();

        return ['nodes' => collect($positioned), 'edges' => $edges];
    }

    // ============ خروجی CSV ============

    public function exportCategoryCsv(): StreamedResponse
    {
        return $this->streamCsv(
            $this->filteredFindings,
            'internal-linking-'.$this->activeCategory.'-'.now()->format('Ymd-His').'.csv'
        );
    }

    public function exportFullReportCsv(): StreamedResponse
    {
        $all = collect(self::CATEGORIES)->keys()->flatMap(fn ($category) => $this->findings[$category] ?? []);

        return $this->streamCsv($all, 'internal-linking-full-report-'.now()->format('Ymd-His').'.csv');
    }

    public function exportSuggestionsCsv(): StreamedResponse
    {
        $rows = $this->pendingSuggestions->map(fn ($s) => [
            'category' => 'suggestion',
            'type' => 'Suggestion',
            'locale' => $s['locale'],
            'title' => $s['source_label'].' → '.$s['target_label'],
            'detail' => "Confidence {$s['confidence']}% — anchor \"{$s['anchor']}\" — {$s['reason']}",
            'edit_url' => $s['source_edit_url'],
        ]);

        return $this->streamCsv($rows, 'internal-linking-suggestions-'.now()->format('Ymd-His').'.csv');
    }

    private function streamCsv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Category', 'Type', 'Locale', 'Item', 'Issue', 'Edit URL']);

            foreach ($rows as $row) {
                fputcsv($out, [
                    self::CATEGORIES[$row['category']] ?? $row['category'],
                    $row['type'],
                    $row['locale'] ? strtoupper($row['locale']) : '—',
                    $row['title'],
                    $row['detail'],
                    $row['edit_url'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
