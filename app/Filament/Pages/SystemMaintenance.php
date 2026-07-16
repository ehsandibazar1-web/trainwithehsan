<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Throwable;

// جایگزین امنِ دو route عمومیِ system-cache-flush / system-migrate در routes/web.php —
// همان دو عملیات را انجام می‌دهد اما فقط برای کاربر لاگین‌شده در پنل قابل دسترسی است
// (بدون نیاز به SSH روی سرور)
class SystemMaintenance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $navigationLabel = 'System Maintenance';

    protected static ?string $title = 'System Maintenance';

    protected string $view = 'filament.pages.system-maintenance';

    public ?string $lastOutput = null;

    public function runMigrations(): void
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
            $this->lastOutput = Artisan::output();

            Notification::make()->success()->title('Migrations ran successfully')->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Migration failed')->body($e->getMessage())->send();
        }
    }

    public function clearCache(): void
    {
        try {
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            $this->lastOutput = 'Cache cleared successfully.';

            Notification::make()->success()->title('Cache cleared successfully')->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Cache clear failed')->body($e->getMessage())->send();
        }
    }
}
