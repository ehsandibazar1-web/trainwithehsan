<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Str;

class ArticleStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $nextScheduled = Article::where('status', 'scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '>', now())
            ->orderBy('published_at')
            ->first();

        return [
            Stat::make('Drafts', Article::where('status', 'draft')->count())
                ->description('پیش‌نویس‌ها')
                ->color('gray'),

            Stat::make('Scheduled', Article::where('status', 'scheduled')->count())
                ->description('زمان‌بندی‌شده')
                ->color('warning'),

            Stat::make('Published', Article::where('status', 'published')->count())
                ->description('منتشرشده')
                ->color('success'),

            Stat::make(
                'This week',
                Article::published()
                    ->whereBetween('published_at', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count()
            )->description('مقالات این هفته'),

            Stat::make(
                'This month',
                Article::published()
                    ->whereYear('published_at', now()->year)
                    ->whereMonth('published_at', now()->month)
                    ->count()
            )->description('مقالات این ماه'),

            Stat::make(
                'Next up',
                $nextScheduled ? Str::limit($nextScheduled->title, 28) : '—'
            )->description(
                $nextScheduled
                    ? $nextScheduled->published_at->format('Y-m-d H:i').' · '.strtoupper($nextScheduled->locale)
                    : 'هیچ مقاله‌ی زمان‌بندی‌شده‌ای در انتظار نیست'
            )->color('info'),
        ];
    }
}
