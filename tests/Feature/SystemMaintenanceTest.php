<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemMaintenance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_storage_link_reports_healthy_when_the_symlink_is_present_and_correct(): void
    {
        // در این محیط public/storage یک symlink سالم به دیسکِ public است
        $healthy = Livewire::actingAs($this->owner())
            ->test(SystemMaintenance::class)
            ->instance()
            ->storageLinkHealthy;

        $this->assertTrue($healthy);
    }

    public function test_storage_link_reports_unhealthy_when_the_link_is_missing(): void
    {
        $link = public_path('storage');
        $backup = $link.'.bak-'.getmypid();

        // symlink را موقتا کنار می‌گذاریم تا شاخه‌ی «لینک نیست» را واقعی بسنجیم — در finally
        // همیشه بازگردانده می‌شود، حتی اگر assertion شکست بخورد
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

    public function test_link_storage_action_recreates_a_missing_link(): void
    {
        $link = public_path('storage');
        $backup = $link.'.bak-'.getmypid();

        // لینک را موقتاً کنار می‌گذاریم تا حالتِ «گم‌شده» را واقعی بسنجیم
        $moved = is_link($link) && @rename($link, $backup);
        $owner = $this->owner();

        try {
            $component = Livewire::actingAs($owner)->test(SystemMaintenance::class);
            $this->assertFalse($component->instance()->storageLinkHealthy);

            $component->call('linkStorage')->assertHasNoErrors();

            // storage:link باید لینک را دوباره ساخته باشد و چکِ سلامت سبز شود
            $this->assertTrue(Livewire::actingAs($owner)->test(SystemMaintenance::class)->instance()->storageLinkHealthy);
        } finally {
            // هرچه اکشن ساخت را بردار و لینکِ اصلی را برگردان
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
