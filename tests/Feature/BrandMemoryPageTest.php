<?php

namespace Tests\Feature;

use App\Filament\Pages\BrandMemory;
use App\Models\BrandMemorySection;
use App\Models\BrandMemoryValue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BrandMemoryPageTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    public function test_page_loads_with_all_default_sections(): void
    {
        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->assertOk()
            ->assertSee('Mission')
            ->assertSee('Writing Tone')
            ->assertSee('Identity');
    }

    public function test_saving_sets_values_and_toggles_for_a_section(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();

        $component = Livewire::actingAs($this->owner())->test(BrandMemory::class);
        $component->set("data.enabled.{$mission->id}", false);
        $component->set("data.values.{$mission->id}.en", 'Teach real self-defense.');
        $component->call('save');

        $mission->refresh();
        $this->assertFalse($mission->is_enabled);
        $this->assertSame('Teach real self-defense.', $mission->valueFor('en')->content);
    }

    public function test_a_blank_value_with_no_existing_row_is_not_persisted(): void
    {
        Livewire::actingAs($this->owner())->test(BrandMemory::class)->call('save');

        $this->assertSame(0, BrandMemoryValue::count());
    }

    public function test_add_custom_section_creates_a_non_system_section(): void
    {
        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->callAction('addSection', data: [
                'label' => 'Training Philosophy',
                'group' => 'Identity',
                'description' => 'How we approach training.',
            ]);

        $section = BrandMemorySection::where('label', 'Training Philosophy')->first();
        $this->assertNotNull($section);
        $this->assertFalse($section->is_system);
        $this->assertSame('Identity', $section->group);
    }

    public function test_delete_custom_section_removes_it(): void
    {
        $custom = BrandMemorySection::create([
            'key' => 'custom_one', 'label' => 'Custom One', 'group' => 'Identity',
            'is_enabled' => true, 'is_system' => false, 'sort_order' => 99,
        ]);

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->callAction('deleteSection', data: ['section_id' => $custom->id]);

        $this->assertNull(BrandMemorySection::find($custom->id));
    }

    public function test_delete_action_cannot_remove_a_system_section(): void
    {
        // یک بخش سفارشی می‌سازیم تا اکشن اصلاً قابل‌مشاهده باشد (وقتی هیچ بخش سفارشی‌ای نیست
        // این اکشن عمداً پنهان است) — سپس تلاش می‌کنیم با شناسه‌ی یک بخش سیستمی (نه از میان
        // گزینه‌های واقعی Select، بلکه مستقیم) صدایش بزنیم تا گارد داخل خودِ اکشن را تست کنیم
        BrandMemorySection::create([
            'key' => 'custom_two', 'label' => 'Custom Two', 'group' => 'Identity',
            'is_enabled' => true, 'is_system' => false, 'sort_order' => 99,
        ]);
        $mission = BrandMemorySection::where('key', 'mission')->first();

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->callAction('deleteSection', data: ['section_id' => $mission->id]);

        $this->assertNotNull(BrandMemorySection::find($mission->id));
    }

    public function test_view_history_shows_previous_versions_of_a_section(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'First version.']);
        $value->update(['content' => 'Second version.']);

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->call('viewHistory', $mission->id)
            ->assertSee('First version.')
            ->assertSee('Second version.');
    }

    public function test_close_history_hides_the_panel_again(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->call('viewHistory', $mission->id)
            ->assertSet('historySectionId', $mission->id)
            ->call('closeHistory')
            ->assertSet('historySectionId', null);
    }

    public function test_restore_version_writes_the_old_content_back_and_logs_a_new_version(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'First version.']);
        $value->update(['content' => 'Second version.']);

        $firstVersionActivity = Activity::where('log_name', 'brand_memory_value')->where('subject_id', $value->id)->oldest('id')->first();

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->call('restoreVersion', $firstVersionActivity->id);

        $this->assertSame('First version.', $value->fresh()->content);
        $this->assertCount(3, Activity::where('log_name', 'brand_memory_value')->where('subject_id', $value->id)->get());
    }

    public function test_preview_prompt_shows_the_exact_system_prompt_including_brand_memory(): void
    {
        $mission = BrandMemorySection::where('key', 'mission')->first();
        BrandMemoryValue::create(['brand_memory_section_id' => $mission->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);

        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->callAction('previewPrompt', data: [
                'field' => 'seo_title',
                'mode' => 'generate',
                'locale' => 'en',
            ])
            ->assertSee('Write a compelling, click-worthy SEO title')
            ->assertSee('Teach real self-defense.');
    }

    public function test_close_preview_clears_the_result(): void
    {
        Livewire::actingAs($this->owner())
            ->test(BrandMemory::class)
            ->callAction('previewPrompt', data: ['field' => 'seo_title', 'mode' => 'generate', 'locale' => 'en'])
            ->assertSet('previewPromptResult', fn ($value) => filled($value))
            ->call('closePreview')
            ->assertSet('previewPromptResult', null);
    }
}
