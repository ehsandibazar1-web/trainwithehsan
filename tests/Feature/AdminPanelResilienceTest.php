<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminPanelResilienceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Deploys land code and migrations separately on this project's no-SSH host (see Deployment
     * Workflow in CLAUDE.md) — the admin panel must stay reachable (including System Maintenance,
     * the only way to run pending migrations) even before this feature's migrations have run.
     */
    public function test_admin_panel_stays_reachable_when_the_notifications_table_does_not_exist_yet(): void
    {
        Schema::dropIfExists('notifications');

        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($user)->get('/admin')->assertOk();
        $this->actingAs($user)->get('/admin/system-maintenance')->assertOk();
        $this->actingAs($user)->get('/admin/articles')->assertOk();
    }

    public function test_admin_panel_loads_normally_once_the_notifications_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('notifications'));

        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($user)->get('/admin')->assertOk();
    }
}
