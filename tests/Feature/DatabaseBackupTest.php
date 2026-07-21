<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemMaintenance;
use App\Models\Article;
use App\Models\User;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PDO;
use Tests\TestCase;

/**
 * بکاپِ دیتابیسِ SQLite — سرویس (اسنپ‌شاتِ سالمِ VACUUM INTO + gzip + چرخش)، فرمانِ
 * زمان‌بندی‌شده‌ی db:backup، و دکمه‌های Backup now / Download در صفحه‌ی System Maintenance.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(app(DatabaseBackupService::class)->directory());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(app(DatabaseBackupService::class)->directory());
        parent::tearDown();
    }

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function service(): DatabaseBackupService
    {
        return app(DatabaseBackupService::class);
    }

    public function test_backup_creates_a_valid_gzipped_sqlite_snapshot(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'Backed Up Article', 'slug' => 'backed-up',
            'body' => '<p>x</p>', 'author_name' => 'Ehsan', 'status' => 'published', 'published_at' => now(),
        ]);

        $result = $this->service()->backup();

        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.sqlite.gz', $result['path']);
        $this->assertGreaterThan(0, $result['size']);

        // اسنپ‌شات باید یک SQLiteِ واقعاً بازشدنی با داده‌های سایت باشد — نه فقط یک فایل
        $restored = sys_get_temp_dir().'/restored-'.uniqid().'.sqlite';
        file_put_contents($restored, gzdecode((string) file_get_contents($result['path'])));

        $pdo = new PDO('sqlite:'.$restored);
        $count = (int) $pdo->query("SELECT COUNT(*) FROM articles WHERE slug = 'backed-up'")->fetchColumn();
        unset($pdo);
        @unlink($restored);

        $this->assertSame(1, $count);
    }

    public function test_rotation_keeps_only_the_newest_backups(): void
    {
        $dir = $this->service()->directory();
        File::ensureDirectoryExists($dir);

        // قدیمی‌ترها با نامِ زمانی-مرتبِ کوچک‌تر — دقیقاً همان قراردادِ نام‌گذاریِ سرویس
        foreach (range(1, DatabaseBackupService::KEEP + 3) as $i) {
            File::put($dir.sprintf('/db-2020-01-%02d-000000-000.sqlite.gz', $i), 'old');
        }

        $this->service()->backup();

        $this->assertCount(DatabaseBackupService::KEEP, $this->service()->list());
        // جدیدترین (بکاپِ واقعیِ همین الان) باید مانده باشد و قدیمی‌ترین‌ها رفته باشند
        $this->assertStringStartsWith('db-20', $this->service()->latest()['name']);
        $this->assertFileDoesNotExist($dir.'/db-2020-01-01-000000-000.sqlite.gz');
    }

    public function test_list_returns_newest_first(): void
    {
        $this->service()->backup();
        $this->service()->backup();

        $list = $this->service()->list();

        $this->assertCount(2, $list);
        $this->assertGreaterThanOrEqual($list[1]['name'], $list[0]['name']);
    }

    public function test_the_scheduled_command_creates_a_backup(): void
    {
        $this->artisan('db:backup')->assertExitCode(0);

        $this->assertNotNull($this->service()->latest());
    }

    public function test_system_maintenance_backup_now_button_creates_a_backup(): void
    {
        Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->call('backupDatabase')
            ->assertNotified();

        $this->assertNotNull($this->service()->latest());
    }

    public function test_system_maintenance_download_button_streams_the_latest_backup(): void
    {
        $this->service()->backup();
        $latest = $this->service()->latest();

        Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->call('downloadLatestBackup')
            ->assertFileDownloaded($latest['name']);
    }

    public function test_download_without_any_backup_warns_instead_of_erroring(): void
    {
        Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->call('downloadLatestBackup')
            ->assertNotified();
    }
}
