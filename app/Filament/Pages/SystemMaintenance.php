<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
            $log = [];

            // این حلقه برای شرایطی است که هاست بدون SSH و با محدودیت زمان اجرا، وسط یک
            // migration قبلی قطع شده باشد: جدول واقعاً ساخته شده ولی در جدول migrations
            // ثبت نشده، پس دفعه‌ی بعد migrate با خطای «already exists» گیر می‌کند. به‌جای
            // گیر کردن، همان migration را «قبلاً اجراشده» علامت می‌زنیم و بقیه را ادامه می‌دهیم.
            $maxAttempts = count(glob(database_path('migrations/*.php'))) + 1;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                try {
                    Artisan::call('migrate', ['--force' => true]);
                    $log[] = Artisan::output();
                    break;
                } catch (QueryException $e) {
                    $migrationName = $this->resolveAlreadyExistingMigration($e);

                    if (! $migrationName) {
                        throw $e;
                    }

                    DB::table('migrations')->insert([
                        'migration' => $migrationName,
                        'batch' => (int) (DB::table('migrations')->max('batch') ?? 0) + 1,
                    ]);

                    $log[] = "Table from '{$migrationName}' already existed on the server — marked as already migrated.";
                }
            }

            $this->lastOutput = implode("\n", array_filter($log));

            Notification::make()->success()->title('Migrations ran successfully')->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Migration failed')->body($e->getMessage())->send();
        }
    }

    // خطای «already exists» را به نام فایل migration ای که هنوز در جدول migrations
    // ثبت نشده وصل می‌کند — فقط برای همین یک نوع خطا؛ خطاهای دیگر دست‌نخورده throw می‌شوند
    private function resolveAlreadyExistingMigration(QueryException $e): ?string
    {
        if (! str_contains($e->getMessage(), 'already exists')) {
            return null;
        }

        if (! preg_match('/table [`"]?(\w+)[`"]?/i', $e->getSql() ?? '', $matches)) {
            return null;
        }

        $table = $matches[1];
        $ran = DB::table('migrations')->pluck('migration')->all();

        foreach (glob(database_path('migrations/*.php')) as $path) {
            $name = basename($path, '.php');

            if (! in_array($name, $ran, true) && str_contains($name, "create_{$table}_table")) {
                return $name;
            }
        }

        return null;
    }

    // آیا symlink ای که فایل‌های دیسکِ public را در دسترسِ عمومی قرار می‌دهد
    // (public/storage → storage/app/public) سالم است؟ اگر این لینک نباشد یا اشتباه باشد،
    // آپلودها موفق می‌شوند (ردیف Media ساخته می‌شود و فایل روی دیسک نوشته می‌شود) ولی هر
    // تصویرِ سایت 404 می‌دهد — نقصی که دقیقا شبیهِ «کتابخانه‌ی رسانه خراب است» به نظر می‌رسد
    // ولی هیچ ربطی به کدِ آپلود ندارد. چون این هاست SSH ندارد، تنها راهِ دیدنِ وضعیتِ لینک
    // همین پنل است. صرفا خواندنی — این صفحه لینک را نمی‌سازد.
    public function getStorageLinkHealthyProperty(): bool
    {
        // دقیقا همان نگاشتی که `php artisan storage:link` می‌سازد را می‌سنجیم
        // (config('filesystems.links') → معمولا public/storage ← storage/app/public).
        // عمدا با ریشه‌ی دیسکِ public مقایسه نمی‌کنیم، چون آن می‌تواند در .env به یک مسیرِ
        // مطلقِ سرورِ تولید تنظیم شده باشد که با هدفِ واقعیِ symlink فرق دارد.
        $links = (array) config('filesystems.links', []);

        if ($links === []) {
            return true;
        }

        foreach ($links as $link => $target) {
            if (! file_exists($link)) {
                return false;
            }

            $linkReal = realpath($link);
            $targetReal = realpath($target);

            if ($linkReal === false || $targetReal === false
                || rtrim($linkReal, DIRECTORY_SEPARATOR) !== rtrim($targetReal, DIRECTORY_SEPARATOR)) {
                return false;
            }
        }

        return true;
    }

    public function getStorageLinkPathProperty(): string
    {
        return (string) (array_key_first((array) config('filesystems.links', [])) ?: public_path('storage'));
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

    // معادلِ یک‌کلیکیِ `php artisan storage:link` از داخلِ پنل — چون این هاست SSH ندارد و همان
    // چکِ سلامتِ بالا ممکن است لینکِ گم‌شده/اشتباه را گزارش کند. یک لینکِ سالم دست‌نخورده می‌ماند؛
    // فقط یک symlinkِ خراب/اشتباه پیش از ساختِ دوباره پاک می‌شود تا storage:link با خطای
    // «already exists» گیر نکند. یک پوشه‌ی واقعی (نه symlink) عمداً حذف نمی‌شود.
    public function linkStorage(): void
    {
        try {
            $link = public_path('storage');

            if (! $this->storageLinkHealthy && is_link($link)) {
                @unlink($link);
            }

            Artisan::call('storage:link');
            $this->lastOutput = trim(Artisan::output()) ?: 'Media storage link created.';

            if ($this->storageLinkHealthy) {
                Notification::make()->success()->title('Media storage link is set up — images will display now')->send();
            } else {
                // symlink() ممکن است روی بعضی هاست‌های اشتراکی غیرفعال باشد
                Notification::make()->warning()
                    ->title('Ran, but the link still is not healthy')
                    ->body('The server may not allow creating symbolic links (symlink disabled), or the web root differs from the app path. Ask your host to enable symlinks or create public/storage manually.')
                    ->send();
            }
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Could not create the media storage link')->body($e->getMessage())->send();
        }
    }
}
