<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);

        // زنگولهٔ اعلان‌ها — App\Notifications\* روی کانال database می‌نویسند، این فقط
        // خواندن/نمایش همان اعلان‌ها را در پنل فعال می‌کند.
        // این ویژگی روی هر صفحهٔ پنل (حتی System Maintenance) در بالای صفحه رندر می‌شود، پس اگر
        // مایگریشن‌های این فیچر روی سرور هنوز اجرا نشده باشند (deploy کد و اجرای مایگریشن دو مرحلهٔ
        // جدا هستند — بدون SSH)، جدول notifications وجود ندارد و کل پنل با خطای 500 روبرو می‌شود.
        // با این گارد، تا وقتی جدول ساخته نشده زنگوله را غیرفعال می‌کنیم تا پنل (و از جمله همین صفحهٔ
        // System Maintenance که برای اجرای مایگریشن لازم است) همیشه در دسترس بماند.
        if (Schema::hasTable('notifications')) {
            $panel = $panel
                ->databaseNotifications()
                ->databaseNotificationsPolling('30s');
        }

        return $panel;
    }
}
