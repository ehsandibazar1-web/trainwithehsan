<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * جایگزین شده با تب Calendar در Content Planner (نگاه کنید به App\Filament\Pages\ContentPlanner) —
 * که همان منطق ماه/هفته/درگ‌اند‌دراپِ این صفحه را دارد، به‌علاوه‌ی Page و پین‌های
 * planned/deadline کارت‌های برنامه‌ریز. این صفحه فقط برای حفظ آدرس قدیمی
 * (/admin/editorial-calendar) نگه داشته شده — از نویگیشن حذف شده تا تقویم تکراری نشان داده نشود.
 */
class EditorialCalendar extends Page
{
    protected string $view = 'filament.pages.editorial-calendar-redirect';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->redirect(ContentPlanner::getUrl().'?view=calendar');
    }
}
