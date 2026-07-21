<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * بکاپِ فایلِ SQLite — تنها دیتابیسِ این سایت. اسنپ‌شات با یک دامپِ ردیف‌به‌ردیف داخلِ یک
 * تراکنشِ خواندن گرفته می‌شود (نه کپیِ خامِ فایل، که اگر وسطِ یک نوشتن انجام شود می‌تواند نسخه‌ی
 * خراب تولید کند — و نه VACUUM INTO، که داخلِ تراکنش مجاز نیست و در محیطِ تست/هر جای
 * تراکنش‌پیچیده می‌شکند). بعد gzip می‌شود و قدیمی‌ترها چرخشی پاک می‌شوند. هم فرمانِ
 * زمان‌بندی‌شده‌ی db:backup و هم دکمه‌های صفحه‌ی System Maintenance از همین یک سرویس استفاده
 * می‌کنند — یک پیاده‌سازی، نه دو.
 *
 * توجه: این بکاپ روی همان دیسکِ سرور است — در برابرِ خطای migration/خرابیِ داده محافظت می‌کند،
 * نه در برابرِ از دست رفتنِ کلِ سرور. برای نسخه‌ی خارج از سرور، دکمه‌ی Download در پنل هست
 * (این هاست SSH ندارد — دانلودِ دستی عملی‌ترین offsite است).
 */
class DatabaseBackupService
{
    // چند نسخه‌ی آخر نگه داشته شود — با بکاپِ روزانه یعنی ~۲ هفته سابقه
    public const KEEP = 14;

    public function directory(): string
    {
        // storage/app عمداً — هرگز زیرِ دیسکِ public (که به وب symlink می‌شود) نه
        return storage_path('app/backups/database');
    }

    /**
     * @return array{path: string, name: string, size: int}
     */
    public function backup(): array
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new RuntimeException('Automatic backups support the SQLite database only.');
        }

        $dir = $this->directory();

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create the backup folder: {$dir}");
        }

        // «v» = میلی‌ثانیه — تا دو بکاپ در یک ثانیه (تست‌ها/کلیک دوبار) روی هم ننویسند؛
        // نامِ فایل‌ها زمانی-مرتب می‌ماند و rotate() بر همان اساس مرتب می‌کند
        $raw = $dir.DIRECTORY_SEPARATOR.'db-'.now()->format('Y-m-d-His-v').'.sqlite';
        $gz = $raw.'.gz';

        try {
            // اگر از قبل داخلِ تراکنش باشیم (محیطِ تست) همان دیدِ سازگار کافی است؛ وگرنه خودمان
            // یک تراکنشِ خواندن باز می‌کنیم تا اسنپ‌شات وسطِ نوشتنِ درخواستِ دیگری نیفتد
            if (DB::getPdo()->inTransaction()) {
                $this->snapshotInto($raw);
            } else {
                DB::transaction(fn () => $this->snapshotInto($raw));
            }

            $this->gzip($raw, $gz);
        } finally {
            @unlink($raw);
        }

        $this->rotate();

        return ['path' => $gz, 'name' => basename($gz), 'size' => (int) (filesize($gz) ?: 0)];
    }

    /**
     * فهرستِ بکاپ‌های موجود، جدیدترین اول.
     *
     * @return array<int, array{path: string, name: string, size: int, created_at: int}>
     */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'db-*.sqlite.gz') ?: [];
        rsort($files);

        return array_map(fn (string $path) => [
            'path' => $path,
            'name' => basename($path),
            'size' => (int) (filesize($path) ?: 0),
            'created_at' => (int) (filemtime($path) ?: 0),
        ], $files);
    }

    /**
     * @return array{path: string, name: string, size: int, created_at: int}|null
     */
    public function latest(): ?array
    {
        return $this->list()[0] ?? null;
    }

    public function rotate(): void
    {
        foreach (array_slice($this->list(), self::KEEP) as $old) {
            @unlink($old['path']);
        }
    }

    // دامپِ کاملِ دیتابیسِ جاری در یک فایلِ SQLiteی تازه: اول schemaی جدول‌ها، بعد داده‌ها
    // (درج‌های آماده‌شده، ردیف‌به‌ردیف)، آخر ایندکس/تریگر/ویو (بعد از داده سریع‌تر ساخته می‌شوند)
    private function snapshotInto(string $path): void
    {
        $target = new PDO('sqlite:'.$path);
        $target->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $target->exec('PRAGMA journal_mode = OFF');
        $target->exec('PRAGMA synchronous = OFF');

        $objects = DB::select(
            "SELECT name, type, sql FROM sqlite_master
             WHERE sql IS NOT NULL AND name NOT LIKE 'sqlite_%'
             ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END"
        );

        $target->exec('BEGIN');

        foreach ($objects as $object) {
            if ($object->type === 'table') {
                $target->exec($object->sql);
                $this->copyTableRows($object->name, $target);
            }
        }

        foreach ($objects as $object) {
            if ($object->type !== 'table') {
                $target->exec($object->sql);
            }
        }

        $target->exec('COMMIT');
    }

    private function copyTableRows(string $table, PDO $target): void
    {
        $quoted = '"'.str_replace('"', '""', $table).'"';
        $source = DB::getPdo()->query("SELECT * FROM {$quoted}");

        $insert = null;

        while (($row = $source->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($insert === null) {
                $columns = implode(', ', array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', array_keys($row)));
                $placeholders = implode(', ', array_fill(0, count($row), '?'));
                $insert = $target->prepare("INSERT INTO {$quoted} ({$columns}) VALUES ({$placeholders})");
            }

            $insert->execute(array_values($row));
        }
    }

    // کپیِ جریانی به gzip — کلِ فایل یک‌جا در حافظه لود نمی‌شود
    private function gzip(string $source, string $destination): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($destination, 'wb6');

        if (! $in || ! $out) {
            throw new RuntimeException('Could not open the backup file for compression.');
        }

        try {
            while (! feof($in)) {
                gzwrite($out, (string) fread($in, 512 * 1024));
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }
}
