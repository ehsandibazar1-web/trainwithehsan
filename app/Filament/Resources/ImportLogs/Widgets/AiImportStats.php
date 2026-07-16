<?php

namespace App\Filament\Resources\ImportLogs\Widgets;

use App\Models\Article;
use App\Models\ImportLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiImportStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $topProvider = ImportLog::whereNotNull('ai_provider')
            ->selectRaw('ai_provider, count(*) as total')
            ->groupBy('ai_provider')
            ->orderByDesc('total')
            ->first();

        return [
            Stat::make('Imported', ImportLog::where('status', 'imported')->count())
                ->description('Articles created by AI import')
                ->color('success'),

            Stat::make('Failed', ImportLog::where('status', 'failed')->count())
                ->description('Rejected by validation')
                ->color('danger'),

            Stat::make('Previews', ImportLog::where('status', 'previewed')->count())
                ->description('Preview runs')
                ->color('info'),

            Stat::make('Rolled back', ImportLog::whereNotNull('rolled_back_at')->count())
                ->description('Imports undone')
                ->color('warning'),

            Stat::make(
                'Draft queue',
                Article::where('status', 'draft')
                    ->whereIn('id', ImportLog::where('status', 'imported')
                        ->whereNull('rolled_back_at')
                        ->whereNotNull('article_id')
                        ->select('article_id'))
                    ->count()
            )->description('Imported drafts awaiting review')
                ->color('gray'),

            Stat::make('Top provider', $topProvider->ai_provider ?? '—')
                ->description($topProvider ? $topProvider->total.' import(s)' : 'No imports yet'),
        ];
    }
}
