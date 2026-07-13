<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;

class EditorialCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Editorial Calendar';

    protected static ?string $title = 'Editorial Calendar';

    protected string $view = 'filament.pages.editorial-calendar';

    public int $year;

    public int $month;

    public function mount(): void
    {
        $this->year = (int) now()->format('Y');
        $this->month = (int) now()->format('n');
    }

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

    // فراخوانی از سمت جاوااسکریپت هنگام رهاکردن مقاله روی یک روز جدید.
    // فقط تاریخ عوض می‌شود (ساعت قبلی حفظ می‌شود)؛ وضعیت را دست‌نخورده می‌گذاریم
    // تا رفتار غیرمنتظره‌ای رخ ندهد — خودِ زمان‌بند لاراول بر اساس همین
    // published_at جدید تصمیم می‌گیرد.
    public function moveArticle(int $articleId, string $newDate): void
    {
        $article = Article::find($articleId);

        if (! $article || ! $article->published_at) {
            return;
        }

        $time = $article->published_at->format('H:i:s');
        $article->update([
            'published_at' => Carbon::parse($newDate.' '.$time),
        ]);

        Notification::make()
            ->success()
            ->title('Moved to '.$newDate)
            ->send();
    }

    public function getCalendarWeeks(): array
    {
        $firstOfMonth = Carbon::create($this->year, $this->month, 1);
        $start = $firstOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $firstOfMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $articles = Article::whereNotNull('published_at')
            ->whereBetween('published_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->orderBy('published_at')
            ->get()
            ->groupBy(fn ($a) => $a->published_at->format('Y-m-d'));

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
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return $weeks;
    }

    public function getMonthLabel(): string
    {
        return Carbon::create($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    public function editUrl(Article $article): string
    {
        return ArticleResource::getUrl('edit', ['record' => $article]);
    }
}
