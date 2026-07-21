<?php

namespace App\Console\Commands;

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

// بکاپِ روزانه‌ی زمان‌بندی‌شده — مثل articles:publish-due همگام اجرا می‌شود (وابسته به صفِ کاری
// نیست، که روی این هاست تضمین‌شده نیست). همان سرویسی که دکمه‌ی Backup nowِ پنل صدا می‌زند.
class BackupDatabase extends Command
{
    protected $signature = 'db:backup';

    protected $description = 'Snapshot the SQLite database into storage/app/backups (gzipped, rotated)';

    public function handle(DatabaseBackupService $backups): int
    {
        try {
            $result = $backups->backup();
        } catch (Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('Backup created: %s (%s KB)', $result['name'], number_format($result['size'] / 1024, 1)));

        return self::SUCCESS;
    }
}
