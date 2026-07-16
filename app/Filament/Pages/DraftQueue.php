<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\ImportLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use UnitEnum;

class DraftQueue extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Draft Queue';

    protected static ?string $title = 'Draft Queue';

    protected string $view = 'filament.pages.draft-queue';

    public function table(Table $table): Table
    {
        return $table
            // پیش‌نویس‌هایی که از ایمپورت هوش مصنوعی آمده‌اند و هنوز منتشر/بازگردانی نشده‌اند
            ->query(Article::query()
                ->where('status', 'draft')
                ->whereIn('id', ImportLog::where('status', 'imported')
                    ->whereNull('rolled_back_at')
                    ->whereNotNull('article_id')
                    ->select('article_id')))
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(45),

                TextColumn::make('locale')
                    ->label('Lang')
                    ->badge(),

                TextColumn::make('category')
                    ->label('Category')
                    ->default('—'),

                TextColumn::make('created_at')
                    ->label('Imported at')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Article $record): string => ArticleResource::getUrl('edit', ['record' => $record])),

                // پیش‌نمایش امضاشده — همان سیستم پیش‌نمایش موجود برای پیش‌نویس‌ها
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Article $record): string => URL::temporarySignedRoute(
                        'articles.preview',
                        now()->addMinutes(30),
                        ['article' => $record->id],
                    ))
                    ->openUrlInNewTab(),

                Action::make('publishNow')
                    ->label('Publish now')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Article $record): string => '"'.$record->title.'" will go live on the site immediately.')
                    ->action(function (Article $record): void {
                        // از طریق Eloquent تا Activity Log رویداد انتشار را ثبت کند
                        $record->update(['status' => 'published', 'published_at' => now()]);

                        Notification::make()->success()->title('Published: '.$record->title)->send();
                    }),
            ])
            ->emptyStateHeading('The draft queue is empty')
            ->emptyStateDescription('Articles imported from AI as drafts appear here until you review and publish them.')
            ->defaultSort('created_at', 'desc');
    }
}
