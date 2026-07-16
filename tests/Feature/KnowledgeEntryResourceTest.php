<?php

namespace Tests\Feature;

use App\Filament\Resources\KnowledgeEntries\Pages\CreateKnowledgeEntry;
use App\Filament\Resources\KnowledgeEntries\Pages\EditKnowledgeEntry;
use App\Filament\Resources\KnowledgeEntries\Pages\ListKnowledgeEntries;
use App\Models\KnowledgeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeEntryResourceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);
    }

    private function makeEntry(array $overrides = []): KnowledgeEntry
    {
        return KnowledgeEntry::create(array_merge([
            'title' => 'Our BJJ program',
            'category' => 'Courses',
            'locale' => 'en',
            'content' => 'We offer beginner through advanced Brazilian Jiu-Jitsu classes.',
        ], $overrides));
    }

    public function test_the_list_page_shows_existing_entries(): void
    {
        $this->makeEntry(['title' => 'Gym Location']);

        $this->actingAs($this->owner())
            ->get('/admin/knowledge-entries')
            ->assertOk()
            ->assertSee('Gym Location');
    }

    public function test_creating_an_entry_works(): void
    {
        Livewire::actingAs($this->owner())
            ->test(CreateKnowledgeEntry::class)
            ->fillForm([
                'title' => 'Founder Biography',
                'category' => 'Biography',
                'locale' => 'en',
                'content' => 'Ehsan has trained for fifteen years.',
                'status' => KnowledgeEntry::STATUS_ACTIVE,
                'priority' => KnowledgeEntry::PRIORITY_HIGH,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('knowledge_entries', [
            'title' => 'Founder Biography',
            'category' => 'Biography',
            'priority' => KnowledgeEntry::PRIORITY_HIGH,
        ]);
    }

    public function test_creating_an_entry_with_an_attachment_registers_a_real_attachment_row(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('brochure.pdf', 100, 'application/pdf');

        Livewire::actingAs($this->owner())
            ->test(CreateKnowledgeEntry::class)
            ->fillForm([
                'title' => 'Gym Brochure',
                'category' => 'Business Information',
                'locale' => 'en',
                'content' => 'See attached brochure for details.',
                'new_attachments' => [$file],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $entry = KnowledgeEntry::where('title', 'Gym Brochure')->firstOrFail();
        $this->assertCount(1, $entry->attachments);
        $this->assertSame('brochure.pdf', $entry->attachments->first()->original_filename);
    }

    public function test_editing_an_entry_updates_it(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->owner())
            ->test(EditKnowledgeEntry::class, ['record' => $entry->getRouteKey()])
            ->fillForm(['content' => 'Updated program description.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated program description.', $entry->fresh()->content);
    }

    public function test_deleting_an_entry_removes_it(): void
    {
        $entry = $this->makeEntry();

        Livewire::actingAs($this->owner())
            ->test(ListKnowledgeEntries::class)
            ->callTableAction('delete', $entry);

        $this->assertDatabaseMissing('knowledge_entries', ['id' => $entry->id]);
    }

    public function test_locale_filter_narrows_the_table_to_that_language(): void
    {
        $this->makeEntry(['title' => 'English fact', 'locale' => 'en']);
        $this->makeEntry(['title' => 'Turkish fact', 'locale' => 'tr']);

        Livewire::actingAs($this->owner())
            ->test(ListKnowledgeEntries::class)
            ->filterTable('locale', 'tr')
            ->assertCanSeeTableRecords(KnowledgeEntry::where('locale', 'tr')->get())
            ->assertCanNotSeeTableRecords(KnowledgeEntry::where('locale', 'en')->get());
    }
}
