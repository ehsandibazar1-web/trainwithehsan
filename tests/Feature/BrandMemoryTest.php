<?php

namespace Tests\Feature;

use App\Models\BrandMemorySection;
use App\Models\BrandMemoryValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BrandMemoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_sections_are_seeded_grouped_and_enabled(): void
    {
        $this->assertSame(25, BrandMemorySection::count());
        $this->assertTrue(BrandMemorySection::where('key', 'mission')->first()->is_enabled);
        $this->assertTrue(BrandMemorySection::where('key', 'mission')->first()->is_system);
        $this->assertSame('Identity', BrandMemorySection::where('key', 'brand_name')->first()->group);
    }

    public function test_section_values_relation_and_value_for_locale(): void
    {
        $section = BrandMemorySection::where('key', 'mission')->first();

        BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Teach real self-defense.']);
        BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'tr', 'content' => 'Gerçek öz savunma öğretmek.']);

        $section->refresh()->load('values');

        $this->assertCount(2, $section->values);
        $this->assertSame('Teach real self-defense.', $section->valueFor('en')->content);
        $this->assertSame('Gerçek öz savunma öğretmek.', $section->valueFor('tr')->content);
        $this->assertNull($section->valueFor('fa'));
    }

    public function test_custom_sections_can_be_added_and_are_not_system(): void
    {
        $custom = BrandMemorySection::create([
            'key' => 'training_philosophy',
            'label' => 'Training Philosophy',
            'group' => 'Identity',
            'is_enabled' => true,
            'is_system' => false,
            'sort_order' => 99,
        ]);

        $this->assertFalse($custom->is_system);
        $this->assertSame(26, BrandMemorySection::count());
    }

    public function test_updating_a_value_logs_activity_for_version_history(): void
    {
        $section = BrandMemorySection::where('key', 'writing_tone')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Confident and direct.']);

        $value->update(['content' => 'Confident, direct, and encouraging.']);

        $updated = Activity::forSubject($value)->where('log_name', 'brand_memory_value')->where('event', 'updated')->get();

        $this->assertCount(1, $updated);
        $this->assertSame('Confident and direct.', $updated->first()->attribute_changes['old']['content']);
        $this->assertSame('Confident, direct, and encouraging.', $updated->first()->attribute_changes['attributes']['content']);
    }

    public function test_a_no_op_save_does_not_log_a_new_version(): void
    {
        $section = BrandMemorySection::where('key', 'writing_tone')->first();
        $value = BrandMemoryValue::create(['brand_memory_section_id' => $section->id, 'locale' => 'en', 'content' => 'Confident and direct.']);

        $value->update(['content' => 'Confident and direct.']);

        $this->assertCount(0, Activity::forSubject($value)->where('log_name', 'brand_memory_value')->where('event', 'updated')->get());
    }
}
