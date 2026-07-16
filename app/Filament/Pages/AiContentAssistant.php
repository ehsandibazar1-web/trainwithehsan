<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Models\Page as PageModel;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * پوسته‌ی نازک اطراف App\Livewire\AiAssistantPanel — تمام منطق واقعی (generate/apply/restore/...)
 * به آن کامپوننت منتقل شده (بدون تغییر) تا هم اینجا (دسترسی مستقیم/لینک‌دهی تمام‌صفحه) و هم
 * سایدبار/کشوی تعبیه‌شده در EditArticle/EditPage از یک کد واحد استفاده کنند. این صفحه عمداً نگه
 * داشته شده — بعد از تعبیه‌ی سایدبار دیگر مسیر اصلی نیست، اما آدرسش هنوز کار می‌کند.
 */
class AiContentAssistant extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?string $title = 'AI Content Assistant';

    protected string $view = 'filament.pages.ai-content-assistant';

    public ?Model $record = null;

    public string $recordType = 'Article';

    public function mount(): void
    {
        $articleId = request()->integer('article');
        $pageId = request()->integer('page');

        if ($articleId) {
            $this->record = Article::find($articleId);
            $this->recordType = 'Article';
        } elseif ($pageId) {
            $this->record = PageModel::find($pageId);
            $this->recordType = 'Page';
        }

        abort_if(! $this->record, 404);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
