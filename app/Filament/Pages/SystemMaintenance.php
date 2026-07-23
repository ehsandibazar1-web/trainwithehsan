<?php

namespace App\Filament\Pages;

use App\Services\Backup\DatabaseBackupService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
    // آیا فایل‌های آپلودشده واقعاً از روی وب قابل‌دسترس‌اند؟ این پاسخِ درستِ «آیا تصویرها نمایش
    // داده می‌شوند» است — فارغ از اینکه هاست چطور سِرو می‌کند. بعضی هاست‌های اشتراکی symlink را
    // غیرفعال می‌کنند ولی دیسکِ public را مستقیم در web root می‌نویسند؛ آنجا تصویرها سالم‌اند
    // هرچند symlinkِ پیش‌فرضِ لاراول وجود ندارد. پس اول دسترس‌پذیریِ واقعی را می‌سنجیم (یک
    // self-request کوچک)، و فقط اگر شبکه در دسترس نبود به چکِ symlink برمی‌گردیم.
    public function getStorageLinkHealthyProperty(): bool
    {
        return $this->publicUploadsAreReachable() || $this->storageSymlinkIsCorrect();
    }

    // یک فایلِ نشانه در دیسکِ public می‌سازد و از طریقِ URLش می‌خواندش — 2xx یعنی تصویرها
    // واقعاً سِرو می‌شوند. هر خطا/تایم‌اوت → false (یعنی «نتوانستم تأیید کنم»، نه لزوماً «خراب»).
    private function publicUploadsAreReachable(): bool
    {
        $marker = '.storage-health-check.txt';
        $token = 'STORAGE_OK';

        try {
            Storage::disk('public')->put($marker, $token);
            $url = Storage::disk('public')->url($marker);

            $ok = Http::timeout(6)->get($url)->successful();
        } catch (Throwable $e) {
            $ok = false;
        } finally {
            try {
                Storage::disk('public')->delete($marker);
            } catch (Throwable $e) {
                // پاک‌سازیِ فایلِ نشانه هرگز نباید نتیجه‌ی چک را عوض کند
            }
        }

        return $ok;
    }

    // fallbackِ بدونِ شبکه: همان نگاشتی که `php artisan storage:link` می‌سازد
    // (config('filesystems.links') → معمولا public/storage ← storage/app/public).
    private function storageSymlinkIsCorrect(): bool
    {
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

    // آیا GDِ این سرور می‌تواند WebP بسازد؟ تبدیلِ خودکارِ تصویرها به WebP به imagewebp() نیاز
    // دارد؛ بعضی هاست‌های اشتراکی GD را بدونِ WebP کامپایل می‌کنند یا imagewebp را غیرفعال
    // می‌کنند — آن‌وقت آپلودها سالم ذخیره می‌شوند ولی نسخه‌ی WebP ساخته نمی‌شود.
    public function getImageWebpSupportedProperty(): bool
    {
        return function_exists('imagewebp') && (bool) (gd_info()['WebP Support'] ?? false);
    }

    // ===== بکاپِ دیتابیس — همان سرویسی که db:backupِ زمان‌بندی‌شده استفاده می‌کند =====

    public function backupDatabase(): void
    {
        try {
            $result = app(DatabaseBackupService::class)->backup();

            $this->lastOutput = sprintf('Backup created: %s (%s KB)', $result['name'], number_format($result['size'] / 1024, 1));

            Notification::make()->success()
                ->title('Backup created')
                ->body('A fresh copy of the database was saved. Use "Download latest backup" to also keep a copy on your own computer.')
                ->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Backup failed')->body($e->getMessage())->send();
        }
    }

    // دانلودِ آخرین بکاپ روی کامپیوترِ خودِ مدیر — روی این هاستِ بدونِ SSH، این عملی‌ترین
    // نسخه‌ی «خارج از سرور» است (بکاپ‌های خودکار روی همان دیسکِ سرورند)
    public function downloadLatestBackup(): ?BinaryFileResponse
    {
        $latest = app(DatabaseBackupService::class)->latest();

        if (! $latest) {
            Notification::make()->warning()->title('No backup exists yet')->body('Click "Backup now" first.')->send();

            return null;
        }

        return response()->download($latest['path'], $latest['name']);
    }

    /**
     * @return array{count: int, latest: array{path: string, name: string, size: int, created_at: int}|null}
     */
    public function getDatabaseBackupStatusProperty(): array
    {
        $backups = app(DatabaseBackupService::class)->list();

        return ['count' => count($backups), 'latest' => $backups[0] ?? null];
    }

    // ===== انتشارِ فایل‌های استاتیکِ طراحی (فونت/تصویر/css/js) به web root =====

    // دقیقاً همان فهرستِ .cpanel.yml — یک منبعِ واحد برای «چه چیزهایی باید به public_html برسند»
    public const PUBLIC_ASSET_DIRS = ['css', 'fonts', 'images', 'js'];

    public const PUBLIC_ASSET_FILES = ['robots.txt', 'BingSiteAuth.xml', 'favicon.ico', 'favicon-16x16.png', 'favicon-32x32.png', 'favicon-512.png', 'apple-touch-icon.png'];

    // مرحله‌ی DeployِcPanel (اجرای .cpanel.yml که این فایل‌ها را به public_html کپی می‌کند) روی
    // این هاست قابل‌اعتماد اجرا نمی‌شود («system cannot deploy») — پس همان کار از داخلِ خودِ اپ
    // انجام می‌شود، همان الگوی runMigrations/linkStorage برای هاستِ بدونِ SSH. web root از
    // تنظیمِ دیسکِ public در می‌آید (root = {web root}/storage — قراردادِ مستندِ config/filesystems.php)
    public function publishStaticAssets(): void
    {
        try {
            $webroot = dirname((string) config('filesystems.disks.public.root'));
            $source = public_path();

            if (! is_dir($webroot)) {
                throw new \RuntimeException("The web root folder was not found: {$webroot}");
            }

            if (realpath($webroot) === realpath($source)) {
                // نصبی که web rootش همان public/ خودِ اپ است (لوکال/سرورِ استاندارد) — چیزی برای کپی نیست
                Notification::make()->success()
                    ->title('Nothing to publish')
                    ->body('On this server the design files are already served directly — no copy needed.')
                    ->send();

                return;
            }

            $published = [];

            foreach (self::PUBLIC_ASSET_DIRS as $dir) {
                if (is_dir($source.DIRECTORY_SEPARATOR.$dir)) {
                    File::copyDirectory($source.DIRECTORY_SEPARATOR.$dir, $webroot.DIRECTORY_SEPARATOR.$dir);
                    $published[] = $dir.'/';
                }
            }

            foreach (self::PUBLIC_ASSET_FILES as $file) {
                if (is_file($source.DIRECTORY_SEPARATOR.$file)) {
                    File::copy($source.DIRECTORY_SEPARATOR.$file, $webroot.DIRECTORY_SEPARATOR.$file);
                    $published[] = $file;
                }
            }

            $this->lastOutput = 'Published to '.$webroot.': '.implode(', ', $published);

            Notification::make()->success()
                ->title('Design files published')
                ->body('Fonts, styles, scripts and design images were copied to the public website folder.')
                ->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Publishing design files failed')->body($e->getMessage())->send();
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

    // معادلِ یک‌کلیکیِ `php artisan optimize` از داخلِ پنل — کشِ config/route/view/event را
    // می‌سازد تا اپ در هر درخواست از صفر bootstrap نشود (ریشه‌ی TTFBِ بالا). چون این هاست SSH
    // ندارد و دیپلویِ cPanel هم گاهی به‌خاطر «uncommitted changes» قفل می‌شود، این دکمه راهِ
    // مطمئنِ اجرای optimize است — با همان PHP 8.4 که خودِ سایت روی آن اجرا می‌شود، پس مشکلِ
    // نسخه‌ی CLI هم ندارد. بعد از ویرایشِ .env (مثلاً APP_DEBUG) هم باید همین زده شود تا config
    // دوباره با مقدار جدید کش شود.
    public function optimizeCache(): void
    {
        try {
            // زیردستورها جداگانه صدا زده می‌شوند، نه umbrellaِ `optimize` — چون آن دستور موقعِ
            // Artisan::call از داخلِ یک درخواستِ وب سب‌کامندهایش را درست resolve نمی‌کند. همان
            // چهار کشِ `php artisan optimize`، به همان الگوی clearCache بالا.
            Artisan::call('config:cache');
            Artisan::call('event:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $this->lastOutput = 'Speed caches rebuilt (config, events, routes, views).';

            Notification::make()->success()->title('Speed cache rebuilt')->send();
        } catch (Throwable $e) {
            $this->lastOutput = $e->getMessage();

            Notification::make()->danger()->title('Rebuild failed')->body($e->getMessage())->send();
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
