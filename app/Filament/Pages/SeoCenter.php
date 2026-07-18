<?php

namespace App\Filament\Pages;

use App\Services\Seo\SeoAuditService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeoCenter extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static ?string $navigationLabel = 'SEO Center';

    protected static ?string $title = 'SEO Center';

    protected string $view = 'filament.pages.seo-center';

    // برچسب‌های قابل‌نمایش هر دسته — ترتیب همان ترتیبی است که در سایدبار نشان داده می‌شود
    public const CATEGORIES = [
        'missing_titles' => 'Missing Meta Titles',
        'missing_descriptions' => 'Missing Meta Descriptions',
        'missing_canonicals' => 'Missing Canonicals',
        'missing_alt' => 'Missing ALT Text',
        'untranslated_alt' => 'Untranslated ALT Text',
        'missing_schema' => 'Missing Schema',
        'duplicate_titles' => 'Duplicate Titles',
        'duplicate_descriptions' => 'Duplicate Descriptions',
        'broken_internal_links' => 'Broken Internal Links',
        'broken_external_links' => 'Broken External Links',
        'orphan_pages' => 'Orphan Pages',
    ];

    /** @var array<string, array<int, array<string, mixed>>> */
    public array $results = [];

    /** @var array<int, array<string, mixed>> */
    public array $externalLinkFindings = [];

    public bool $hasScannedExternalLinks = false;

    public string $activeCategory = 'missing_titles';

    public string $localeFilter = 'all';

    public string $typeFilter = 'all';

    public string $search = '';

    public function mount(): void
    {
        $this->runAudit();
    }

    // بررسی‌های سریع (بدون تماس شبکه‌ای) — طبق طراحی هر بار که صفحه باز می‌شود اجرا می‌شوند،
    // چون فقط کوئری‌های دیتابیس‌اند (شبیه محاسبه‌ی گرید کتابخانه‌ی رسانه)
    public function runAudit(): void
    {
        $this->results = app(SeoAuditService::class)->run();

        Notification::make()->success()->title('SEO audit refreshed')->send();
    }

    // این یکی عمداً دستی است — تماس‌های HTTP واقعی به سایت‌های بیرونی می‌زند و کند است
    public function scanExternalLinks(): void
    {
        $this->externalLinkFindings = app(SeoAuditService::class)->checkExternalLinks();
        $this->hasScannedExternalLinks = true;

        Notification::make()
            ->success()
            ->title(count($this->externalLinkFindings).' broken external link(s) found')
            ->send();
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
            $counts[$key] = $key === 'broken_external_links'
                ? count($this->externalLinkFindings)
                : count($this->results[$key] ?? []);
        }

        return $counts;
    }

    public function getTotalIssuesProperty(): int
    {
        return array_sum($this->categoryCounts);
    }

    private function rawFindingsForCategory(string $category): array
    {
        return $category === 'broken_external_links'
            ? $this->externalLinkFindings
            : ($this->results[$category] ?? []);
    }

    public function getFilteredFindingsProperty(): Collection
    {
        return $this->applyFilters(collect($this->rawFindingsForCategory($this->activeCategory)));
    }

    private function applyFilters(Collection $findings): Collection
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
        return collect($this->rawFindingsForCategory($this->activeCategory))
            ->pluck('type')->unique()->sort()->values()->all();
    }

    // خروجی CSV فقط از دسته‌ی فعال، با فیلترهای اعمال‌شده
    public function exportCategoryCsv(): StreamedResponse
    {
        return $this->streamCsv(
            $this->filteredFindings,
            'seo-audit-'.$this->activeCategory.'-'.now()->format('Ymd-His').'.csv'
        );
    }

    // خروجی CSV کامل — همه‌ی دسته‌ها با هم، بدون فیلتر (گزارش کلی برای اشتراک‌گذاری)
    public function exportFullReportCsv(): StreamedResponse
    {
        $all = collect(self::CATEGORIES)->keys()
            ->flatMap(fn ($category) => $this->rawFindingsForCategory($category));

        return $this->streamCsv($all, 'seo-audit-full-report-'.now()->format('Ymd-His').'.csv');
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
