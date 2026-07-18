<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_reports_healthy_when_uploaded_files_are_reachable_even_without_a_symlink(): void
    {
        // بعضی هاست‌ها symlink را غیرفعال می‌کنند ولی فایل‌ها را مستقیم سِرو می‌کنند — اگر URLِ
        // فایلِ نشانه 2xx بدهد یعنی تصویرها واقعاً کار می‌کنند، فارغ از وضعیتِ symlink
        Http::fake(['*' => Http::response('STORAGE_OK', 200)]);

        $healthy = Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->instance()
            ->storageLinkHealthy;

        $this->assertTrue($healthy);
    }

    public function test_falls_back_to_the_symlink_check_when_the_self_request_is_not_reachable(): void
    {
        // اگر self-request در دسترس نبود (اینجا با 404 شبیه‌سازی شده)، به چکِ symlink برمی‌گردیم؛
        // در این محیط public/storage یک symlinkِ سالم است، پس نتیجه باز هم سالم است
        Http::fake(['*' => Http::response('nope', 404)]);

        $healthy = Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->instance()
            ->storageLinkHealthy;

        $this->assertTrue($healthy);
    }

    public function test_reports_unhealthy_only_when_unreachable_and_symlink_missing(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);

        $link = public_path('storage');
        $backup = $link.'.bak-'.getmypid();
        // symlink را موقتا کنار می‌گذاریم؛ در finally همیشه بازگردانده می‌شود
        $moved = file_exists($link) && @rename($link, $backup);

        try {
            $healthy = Livewire::actingAs($this->owner())
                ->test(SystemMaintenance::class)
                ->instance()
                ->storageLinkHealthy;

            $this->assertFalse($healthy);
        } finally {
            if ($moved) {
                @rename($backup, $link);
            }
        }
    }

    public function test_image_webp_support_property_reflects_the_server(): void
    {
        $supported = Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->instance()
            ->imageWebpSupported;

        // یک بولین است و باید با قابلیتِ واقعیِ GDِ همین محیط بخواند
        $this->assertSame(function_exists('imagewebp') && (bool) (gd_info()['WebP Support'] ?? false), $supported);
    }

    public function test_link_storage_action_recreates_a_missing_link(): void
    {
        // reachability را ناموفق نگه می‌داریم تا واقعاً مسیرِ symlink سنجیده شود
        Http::fake(['*' => Http::response('nope', 404)]);

        $link = public_path('storage');
        $backup = $link.'.bak-'.getmypid();
        $moved = is_link($link) && @rename($link, $backup);
        $owner = $this->owner();

        try {
            $component = Livewire::actingAs($owner)->test(SystemMaintenance::class);
            $this->assertFalse($component->instance()->storageLinkHealthy);

            $component->call('linkStorage')->assertHasNoErrors();

            // storage:link باید لینک را دوباره ساخته باشد و چکِ سلامت (از مسیرِ symlink) سبز شود
            $this->assertTrue(Livewire::actingAs($owner)->test(SystemMaintenance::class)->instance()->storageLinkHealthy);
        } finally {
            if ($moved) {
                if (is_link($link) || file_exists($link)) {
                    @unlink($link);
                }
                @rename($backup, $link);
            }
        }
    }

    public function test_link_storage_action_is_harmless_when_already_healthy(): void
    {
        Http::fake(['*' => Http::response('nope', 404)]);
        $owner = $this->owner();

        // لینکِ سالمِ موجود نباید خراب شود — اجرای دوباره بی‌ضرر است
        Livewire::actingAs($owner)
            ->test(SystemMaintenance::class)
            ->call('linkStorage')
            ->assertHasNoErrors();

        $this->assertTrue(
            Livewire::actingAs($owner)->test(SystemMaintenance::class)->instance()->storageLinkHealthy
        );
    }
}
