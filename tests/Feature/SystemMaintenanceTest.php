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
}
