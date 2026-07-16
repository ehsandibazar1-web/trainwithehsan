<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\KnowledgeEntry;
use App\Models\KnowledgeEntryAttachment;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class KnowledgeBaseTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(array $overrides = []): KnowledgeEntry
    {
        return KnowledgeEntry::create(array_merge([
            'title' => 'Our BJJ program',
            'category' => 'Courses',
            'locale' => 'en',
            'content' => 'We offer beginner through advanced Brazilian Jiu-Jitsu classes.',
        ], $overrides));
    }

    public function test_entry_defaults_to_active_medium_priority_and_not_pinned(): void
    {
        $entry = $this->makeEntry();

        $this->assertSame(KnowledgeEntry::STATUS_ACTIVE, $entry->status);
        $this->assertSame(KnowledgeEntry::PRIORITY_MEDIUM, $entry->priority);
        $this->assertFalse($entry->is_pinned);
        $this->assertNull($entry->expires_at);
    }

    public function test_available_scope_excludes_inactive_and_expired_entries(): void
    {
        $active = $this->makeEntry(['title' => 'Active']);
        $draft = $this->makeEntry(['title' => 'Draft', 'status' => KnowledgeEntry::STATUS_DRAFT]);
        $archived = $this->makeEntry(['title' => 'Archived', 'status' => KnowledgeEntry::STATUS_ARCHIVED]);
        $expired = $this->makeEntry(['title' => 'Expired', 'expires_at' => now()->subDay()]);
        $futureExpiry = $this->makeEntry(['title' => 'Future expiry', 'expires_at' => now()->addDay()]);

        $available = KnowledgeEntry::available()->pluck('title')->all();

        $this->assertContains('Active', $available);
        $this->assertContains('Future expiry', $available);
        $this->assertNotContains('Draft', $available);
        $this->assertNotContains('Archived', $available);
        $this->assertNotContains('Expired', $available);
    }

    public function test_is_expired_reflects_expires_at(): void
    {
        $this->assertTrue($this->makeEntry(['expires_at' => now()->subMinute()])->isExpired());
        $this->assertFalse($this->makeEntry(['expires_at' => now()->addMinute()])->isExpired());
        $this->assertFalse($this->makeEntry()->isExpired());
    }

    public function test_entry_can_have_tags_via_the_existing_tag_model(): void
    {
        $entry = $this->makeEntry();
        $tag = Tag::create(['name' => 'Beginner Friendly']);

        $entry->tags()->attach($tag);

        $this->assertTrue($entry->fresh()->tags->contains('id', $tag->id));
        $this->assertTrue($tag->knowledgeEntries->contains('id', $entry->id));
    }

    public function test_entry_can_have_attachments(): void
    {
        $entry = $this->makeEntry();
        $attachment = KnowledgeEntryAttachment::create([
            'knowledge_entry_id' => $entry->id,
            'disk_path' => 'knowledge-base/brochure.pdf',
            'original_filename' => 'brochure.pdf',
            'mime_type' => 'application/pdf',
            'size' => 12345,
        ]);

        $this->assertCount(1, $entry->fresh()->attachments);
        $this->assertSame($entry->id, $attachment->knowledgeEntry->id);
    }

    public function test_updating_content_logs_activity_for_version_history(): void
    {
        $entry = $this->makeEntry();
        $entry->update(['content' => 'Updated program description.']);

        $activity = Activity::where('log_name', 'knowledge_entry')
            ->where('subject_id', $entry->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($activity);
        $this->assertSame('Updated program description.', $activity->attribute_changes['attributes']['content']);
    }

    public function test_ai_generation_can_record_which_knowledge_entries_were_used(): void
    {
        $entry = $this->makeEntry();
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => 1, 'field' => 'body', 'mode' => 'improve', 'status' => 'completed',
        ]);

        $generation->knowledgeEntries()->attach($entry->id);

        $this->assertTrue($generation->fresh()->knowledgeEntries->contains('id', $entry->id));
        $this->assertTrue($entry->fresh()->generations->contains('id', $generation->id));
    }
}
