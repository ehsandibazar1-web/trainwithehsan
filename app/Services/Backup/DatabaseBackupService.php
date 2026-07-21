<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * بکاپِ دیتابیس — SQLite (توسعه/تست) و MySQL (پروداکشنِ واقعیِ این پروژه، کشفِ 2026-07-21). اسنپ‌شات با یک دامپِ ردیف‌به‌ردیف داخلِ یک
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
        // پروداکشنِ این پروژه در عمل MySQL است (کشفِ 2026-07-21 — همین گاردِ اولیه‌ی sqlite-فقط
        // روی سرورِ واقعی خطا داد)، محیطِ توسعه/تست SQLite — هر دو پشتیبانی می‌شوند
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            throw new RuntimeException("Automatic backups support SQLite and MySQL only (this connection is: {$driver}).");
        }

        $dir = $this->directory();

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create the backup folder: {$dir}");
        }

        // «v» = میلی‌ثانیه — تا دو بکاپ در یک ثانیه (تست‌ها/کلیک دوبار) روی هم ننویسند؛
        // نامِ فایل‌ها زمانی-مرتب می‌ماند و rotate() بر همان اساس مرتب می‌کند. پسوند بسته به
        // درایور: اسنپ‌شاتِ فایلی برای SQLite، دامپِ SQLِ قابلِ‌ایمپورت (phpMyAdmin) برای MySQL
        $raw = $dir.DIRECTORY_SEPARATOR.'db-'.now()->format('Y-m-d-His-v').($driver === 'sqlite' ? '.sqlite' : '.sql');
        $gz = $raw.'.gz';

        try {
            // اگر از قبل داخلِ تراکنش باشیم (محیطِ تست) همان دیدِ سازگار کافی است؛ وگرنه خودمان
            // یک تراکنشِ خواندن باز می‌کنیم تا اسنپ‌شات وسطِ نوشتنِ درخواستِ دیگری نیفتد
            // (InnoDB با REPEATABLE READ دیدِ سازگار می‌دهد، SQLite هم همین‌طور)
            $snapshot = fn () => $driver === 'sqlite' ? $this->snapshotInto($raw) : $this->dumpMysqlInto($raw);

            if (DB::getPdo()->inTransaction()) {
                $snapshot();
            } else {
                DB::transaction($snapshot);
            }

            $this->gzip($raw, $gz);
        } finally {
            @unlink($raw);
        }

        $this->rotate();

        return ['path' => $gz, 'name' => basename($gz), 'size' => (int) (filesize($gz) ?: 0)];
    }

    /**
     * فهرستِ بکاپ‌های موجود، جدیدترین اول — هر دو فرمت (sqlite.gz و sql.gz).
     *
     * @return array<int, array{path: string, name: string, size: int, created_at: int}>
     */
    public function list(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'db-*.gz') ?: [];
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

    // دامپِ SQLِ خالصِ PHP برای MySQL — بدونِ mysqldump (این هاست SSH ندارد و exec ممکن است بسته
    // باشد). خروجی یک .sql استاندارد است که با phpMyAdminِ همان cPanel ایمپورت/بازیابی می‌شود.
    // FOREIGN_KEY_CHECKS=0 در ابتدای فایل تا ترتیبِ جدول‌ها هنگامِ بازیابی مهم نباشد.
    private function dumpMysqlInto(string $path): void
    {
        $pdo = DB::getPdo();
        $out = fopen($path, 'wb');

        if (! $out) {
            throw new RuntimeException("Could not open the backup file for writing: {$path}");
        }

        try {
            fwrite($out, '-- Database backup — '.now()->toDateTimeString()."\n");
            fwrite($out, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = array_map(fn ($row) => (string) array_values((array) $row)[0], DB::select('SHOW TABLES'));

            foreach ($tables as $table) {
                $quoted = '`'.str_replace('`', '``', $table).'`';

                $create = array_values((array) DB::selectOne("SHOW CREATE TABLE {$quoted}"))[1] ?? null;

                if (! is_string($create)) {
                    throw new RuntimeException("Could not read the structure of table {$table}.");
                }

                fwrite($out, "DROP TABLE IF EXISTS {$quoted};\n{$create};\n\n");

                $rows = $pdo->query("SELECT * FROM {$quoted}");
                $batch = [];
                $columns = null;

                while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                    $columns ??= '('.implode(', ', array_map(fn ($c) => '`'.str_replace('`', '``', (string) $c).'`', array_keys($row))).')';
                    $batch[] = '('.implode(', ', array_map(fn ($v) => $this->sqlValue($pdo, $v), array_values($row))).')';

                    // درج‌های دسته‌ای ۱۰۰تایی — فایلِ کوچک‌تر و بازیابیِ سریع‌تر از درجِ تک‌ردیفی
                    if (count($batch) === 100) {
                        fwrite($out, "INSERT INTO {$quoted} {$columns} VALUES\n".implode(",\n", $batch).";\n");
                        $batch = [];
                    }
                }

                if ($batch !== []) {
                    fwrite($out, "INSERT INTO {$quoted} {$columns} VALUES\n".implode(",\n", $batch).";\n");
                }

                fwrite($out, "\n");
            }

            fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($out);
        }
    }

    // یک مقدارِ ستون → لیترالِ امنِ SQL؛ NULL همان NULL، بقیه با quoteِ خودِ درایور
    private function sqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return (string) $pdo->quote((string) $value);
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
