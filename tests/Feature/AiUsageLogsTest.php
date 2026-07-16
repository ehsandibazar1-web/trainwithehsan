<?php

namespace Tests\Feature;

use App\Filament\Resources\AiUsageLogs\AiUsageLogResource;
use App\Filament\Resources\AiUsageLogs\Pages\ListAiUsageLogs;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiUsageLogsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_usage_logs_resource_lists_rows_and_is_fully_read_only(): void
    {
        $log = AiUsageLog::create([
            'provider_slug' => 'openai',
            'model' => 'gpt-5',
            'action_key' => 'seo_title',
            'prompt_tokens' => 100,
            'completion_tokens' => 20,
            'total_tokens' => 120,
            'estimated_cost_usd' => 0.0012,
            'response_time_ms' => 850,
            'status' => 'success',
        ]);

        $this->actingAs($this->owner())
            ->get('/admin/ai-usage-logs')
            ->assertOk()
            ->assertSee('openai')
            ->assertSee('gpt-5');

        $this->assertFalse(AiUsageLogResource::canCreate());
        $this->assertFalse(AiUsageLogResource::canEdit($log));
        $this->assertFalse(AiUsageLogResource::canDelete($log));
    }

    public function test_failed_logs_show_an_error_detail_action_and_successful_ones_do_not(): void
    {
        $failed = AiUsageLog::create([
            'provider_slug' => 'openai',
            'status' => 'failed',
            'error_message' => 'Request failed: rate limited',
        ]);
        $success = AiUsageLog::create(['provider_slug' => 'openai', 'status' => 'success']);

        $component = Livewire::actingAs($this->owner())->test(ListAiUsageLogs::class);

        $component->assertTableActionVisible('errorDetail', $failed);
        $component->assertTableActionHidden('errorDetail', $success);
    }

    public function test_export_csv_bulk_action_streams_a_csv_of_selected_rows(): void
    {
        $log = AiUsageLog::create([
            'provider_slug' => 'anthropic',
            'model' => 'claude-sonnet-4-5',
            'action_key' => 'translate',
            'prompt_tokens' => 50,
            'completion_tokens' => 10,
            'total_tokens' => 60,
            'response_time_ms' => 400,
            'status' => 'success',
        ]);

        $response = Livewire::actingAs($this->owner())
            ->test(ListAiUsageLogs::class)
            ->callTableBulkAction('exportCsv', [$log->id]);

        $response->assertSuccessful();
    }

    public function test_filtering_by_provider_and_status_narrows_the_table(): void
    {
        AiUsageLog::create(['provider_slug' => 'openai', 'status' => 'success']);
        AiUsageLog::create(['provider_slug' => 'anthropic', 'status' => 'failed']);

        Livewire::actingAs($this->owner())
            ->test(ListAiUsageLogs::class)
            ->filterTable('provider_slug', 'openai')
            ->assertCanSeeTableRecords(AiUsageLog::where('provider_slug', 'openai')->get())
            ->assertCanNotSeeTableRecords(AiUsageLog::where('provider_slug', 'anthropic')->get());
    }
}
