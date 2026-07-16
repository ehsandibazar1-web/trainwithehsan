<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\ImportLog;
use App\Models\Media;
use App\Models\User;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiImportTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ArticleImportService
    {
        return app(ArticleImportService::class);
    }

    private function validJson(array $overrides = []): string
    {
        return json_encode(array_merge([
            'language' => 'en',
            'title' => 'Imported Article',
            'excerpt' => 'A standalone excerpt.',
            'content' => '<p>Body content.</p>',
            'category' => 'Guides',
            'publish_status' => 'published',
            'provider' => 'claude',
        ], $overrides));
    }

    public function test_valid_json_import_maps_all_fields(): void
    {
        $result = $this->service()->import($this->validJson([
            'faq' => [['question' => 'Q?', 'answer' => 'A.']],
            'publish_date' => '2026-07-01 08:00',
        ]));

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertNotNull($article);
        $this->assertSame('en', $article->locale);
        $this->assertSame('Imported Article', $article->title);
        $this->assertSame('imported-article', $article->slug);
        $this->assertSame('A standalone excerpt.', $article->excerpt);
        $this->assertSame('<p>Body content.</p>', $article->body);
        $this->assertSame('Guides', $article->category);
        $this->assertSame('published', $article->status);
        $this->assertSame('2026-07-01 08:00', $article->published_at->format('Y-m-d H:i'));
        $this->assertSame([['question' => 'Q?', 'answer' => 'A.']], $article->faqs);
        $this->assertSame('Ehsan Dibazar', $article->author_name);
        $this->assertSame(1, $article->reading_time);
    }

    public function test_import_writes_log_with_counts_and_provider(): void
    {
        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->service()->import(
            $this->validJson(['faq' => [
                ['question' => 'Q1?', 'answer' => 'A1.'],
                ['question' => 'Q2?', 'answer' => 'A2.'],
            ]]),
            'auto',
            ['user_id' => $user->id, 'source' => 'panel'],
        );

        $log = ImportLog::first();
        $this->assertSame('imported', $log->status);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('claude', $log->ai_provider);
        $this->assertSame('json', $log->format);
        $this->assertSame('Imported Article', $log->article_title);
        $this->assertSame(2, $log->faq_count);
        $this->assertSame(0, $log->image_count);
        $this->assertNotNull($log->article_id);
    }

    public function test_missing_title_and_content_are_rejected(): void
    {
        $analysis = $this->service()->analyze('{"language": "en"}');

        $this->assertContains('Missing title.', $analysis['errors']);
        $this->assertContains('Missing content.', $analysis['errors']);
    }

    public function test_invalid_json_is_rejected(): void
    {
        $analysis = $this->service()->analyze('{not json');

        $this->assertNotEmpty($analysis['errors']);
        $this->assertStringContainsString('Invalid JSON', $analysis['errors'][0]);
    }

    public function test_duplicate_slug_is_rejected_per_locale(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'Existing', 'slug' => 'imported-article',
            'body' => 'x', 'status' => 'published', 'published_at' => now(),
        ]);

        $analysis = $this->service()->analyze($this->validJson());
        $this->assertStringContainsString('already exists', implode(' ', $analysis['errors']));

        // همان اسلاگ برای زبان دیگر آزاد است — مدل دو-ردیفه
        $analysisTr = $this->service()->analyze($this->validJson(['language' => 'tr']));
        $this->assertSame([], $analysisTr['errors']);
    }

    public function test_invalid_faq_is_rejected(): void
    {
        $analysis = $this->service()->analyze($this->validJson([
            'faq' => [['question' => 'Only a question, no answer']],
        ]));

        $this->assertStringContainsString('Invalid FAQ item #1', implode(' ', $analysis['errors']));
    }

    public function test_scheduled_without_date_is_rejected(): void
    {
        $analysis = $this->service()->analyze($this->validJson(['publish_status' => 'scheduled']));

        $this->assertContains('A "scheduled" article needs a publish date.', $analysis['errors']);
    }

    public function test_invalid_publish_date_is_rejected(): void
    {
        $analysis = $this->service()->analyze($this->validJson(['publish_date' => 'not-a-date']));

        $this->assertStringContainsString('Invalid publish date', implode(' ', $analysis['errors']));
    }

    public function test_scheduled_import_uses_existing_scheduling_workflow(): void
    {
        $result = $this->service()->import($this->validJson([
            'publish_status' => 'scheduled',
            'publish_date' => now()->addDays(3)->format('Y-m-d H:i'),
        ]));

        $this->assertSame('scheduled', $result['article']->status);
        // زمان‌بندی‌شده‌ی آینده نباید در فهرست عمومی دیده شود — همان scopePublished موجود
        $this->assertSame(0, Article::published()->count());
    }

    public function test_draft_status_saves_draft(): void
    {
        $result = $this->service()->import($this->validJson(['publish_status' => 'draft']));

        $this->assertSame('draft', $result['article']->status);
    }

    public function test_missing_status_with_future_date_becomes_scheduled(): void
    {
        $json = $this->validJson(['publish_date' => now()->addWeek()->format('Y-m-d H:i')]);
        $json = json_decode($json, true);
        unset($json['publish_status']);

        $result = $this->service()->import(json_encode($json));

        $this->assertSame('scheduled', $result['article']->status);
    }

    public function test_markdown_import_with_front_matter_and_faq_section(): void
    {
        $md = "---\nlanguage: en\ntitle: Markdown Post\npublish_status: draft\n---\n# Heading\n\nBody **text**.\n\n## FAQ\n\n### First question?\nFirst answer.\n";

        $result = $this->service()->import($md, 'markdown');

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertStringContainsString('<h1>Heading</h1>', $article->body);
        $this->assertStringContainsString('<strong>text</strong>', $article->body);
        $this->assertStringNotContainsString('FAQ', $article->body);
        $this->assertSame([['question' => 'First question?', 'answer' => 'First answer.']], $article->faqs);
    }

    public function test_translation_of_links_to_existing_article_by_slug(): void
    {
        $en = Article::create([
            'locale' => 'en', 'title' => 'Original', 'slug' => 'original',
            'body' => 'x', 'status' => 'published', 'published_at' => now(),
        ]);

        $result = $this->service()->import($this->validJson([
            'language' => 'tr', 'title' => 'Çeviri', 'translation_of' => 'original',
        ]));

        $this->assertSame($en->id, $result['article']->translation_of);
    }

    public function test_translation_of_unknown_slug_is_rejected(): void
    {
        $analysis = $this->service()->analyze($this->validJson(['translation_of' => 'ghost-slug']));

        $this->assertStringContainsString('translation_of refers to', implode(' ', $analysis['errors']));
    }

    public function test_featured_image_url_is_downloaded_into_media_library(): void
    {
        Storage::fake('public');

        $img = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();

        Http::fake(['cdn.example.com/*' => Http::response($png, 200)]);

        $result = $this->service()->import($this->validJson([
            'featured_image' => 'https://cdn.example.com/photo.png',
        ]));

        $this->assertSame([], $result['errors']);
        $article = $result['article'];
        $this->assertNotNull($article->image_path);
        Storage::disk('public')->assertExists($article->image_path);

        $media = Media::first();
        $this->assertSame('image', $media->type);
        $this->assertSame('image/png', $media->mime_type);
        $this->assertSame($article->image_path, $media->disk_path);

        $this->assertSame(1, ImportLog::first()->image_count);
    }

    public function test_featured_image_existing_path_is_reused_without_new_media_row(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('articles/existing.jpg', 'fake');

        $result = $this->service()->import($this->validJson([
            'featured_image' => 'articles/existing.jpg',
        ]));

        $this->assertSame('articles/existing.jpg', $result['article']->image_path);
        $this->assertSame(0, Media::count());
    }

    public function test_missing_image_path_is_rejected(): void
    {
        Storage::fake('public');

        $analysis = $this->service()->analyze($this->validJson([
            'featured_image' => 'articles/nope.jpg',
        ]));

        $this->assertStringContainsString('not found in the media library', implode(' ', $analysis['errors']));
    }

    public function test_non_image_download_fails_and_logs_failure(): void
    {
        Storage::fake('public');
        Http::fake(['cdn.example.com/*' => Http::response('not an image', 200)]);

        $result = $this->service()->import($this->validJson([
            'featured_image' => 'https://cdn.example.com/file.bin',
        ]));

        $this->assertNull($result['article']);
        $this->assertStringContainsString('not a supported image', implode(' ', $result['errors']));
        $this->assertSame('failed', ImportLog::first()->status);
    }

    public function test_tags_and_seo_extras_warn_but_do_not_block(): void
    {
        $analysis = $this->service()->analyze($this->validJson([
            'tags' => ['a', 'b'],
            'og' => ['title' => 'x'],
            'canonical' => 'https://example.com',
            'robots' => 'index,follow',
            'schema' => ['type' => 'Article'],
        ]));

        $this->assertSame([], $analysis['errors']);
        $this->assertArrayHasKey('tags', $analysis['mapping']['skipped']);
        $this->assertArrayHasKey('og', $analysis['mapping']['auto']);
        $this->assertArrayHasKey('canonical', $analysis['mapping']['auto']);
        $this->assertArrayHasKey('robots', $analysis['mapping']['auto']);
        $this->assertArrayHasKey('schema', $analysis['mapping']['auto']);
    }

    public function test_seo_meta_description_fills_missing_excerpt(): void
    {
        $json = json_decode($this->validJson(), true);
        unset($json['excerpt']);
        $json['seo'] = ['meta_description' => 'From SEO field.'];

        $result = $this->service()->import(json_encode($json));

        $this->assertSame('From SEO field.', $result['article']->excerpt);
        $this->assertSame('From SEO field.', $result['article']->meta_description);
    }

    public function test_meta_description_column_is_populated_from_excerpt(): void
    {
        $result = $this->service()->import($this->validJson());

        $this->assertSame([], $result['errors']);
        $this->assertSame('A standalone excerpt.', $result['article']->excerpt);
        $this->assertSame('A standalone excerpt.', $result['article']->meta_description);
    }

    public function test_missing_excerpt_and_seo_description_leaves_meta_description_null(): void
    {
        $json = json_decode($this->validJson(), true);
        unset($json['excerpt']);

        $result = $this->service()->import(json_encode($json));

        $this->assertSame([], $result['errors']);
        $this->assertNull($result['article']->meta_description);
    }

    public function test_seo_title_is_saved_on_the_article(): void
    {
        $result = $this->service()->import($this->validJson([
            'seo' => ['title' => 'A Custom SEO Title'],
        ]));

        $this->assertSame([], $result['errors']);
        $this->assertSame('A Custom SEO Title', $result['article']->seo_title);
    }

    public function test_missing_seo_title_leaves_the_column_null(): void
    {
        $result = $this->service()->import($this->validJson());

        $this->assertSame([], $result['errors']);
        $this->assertNull($result['article']->seo_title);
    }

    public function test_long_seo_title_warns_but_still_saves(): void
    {
        $long = str_repeat('x', 80);

        $analysis = $this->service()->analyze($this->validJson([
            'seo' => ['title' => $long],
        ]));

        $this->assertSame($long, $analysis['payload']['seo_title']);
        $this->assertNotEmpty(array_filter(
            $analysis['warnings'],
            fn (string $w) => str_contains($w, 'SEO title is longer than')
        ));
    }

    public function test_failed_validation_import_writes_failed_log(): void
    {
        $result = $this->service()->import('{"language": "en"}');

        $this->assertNull($result['article']);
        $log = ImportLog::first();
        $this->assertSame('failed', $log->status);
        $this->assertContains('Missing title.', $log->errors);
        $this->assertSame(0, Article::count());
    }

    public function test_activity_log_records_imported_article(): void
    {
        $this->service()->import($this->validJson());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'article',
            'description' => 'Article created',
        ]);
    }

    public function test_admin_ai_import_page_renders_for_owner(): void
    {
        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($user)
            ->get('/admin/ai-import')
            ->assertOk()
            ->assertSee('AI Import')
            ->assertSee('Validate')
            ->assertSee('Preview')
            ->assertSee('Import');
    }
}
