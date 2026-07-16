<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiChatMessage;
use App\Jobs\RunAiContentGeneration;
use App\Jobs\TranslateArticleDraft;
use App\Livewire\AiAssistantPanel;
use App\Models\AiChatMessage;
use App\Models\AiGeneration;
use App\Models\Article;
use App\Models\Media;
use App\Models\Page;
use App\Services\AiAssistant\ActionRegistry;
use App\Services\AiAssistant\ContentAssistantService;
use App\Services\AiAssistant\ContentReviewService;
use App\Services\AiAssistant\DiffService;
use App\Services\ArticleImport\ArticleImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistantPanelTest extends TestCase
{
    use RefreshDatabase;

    private function makeArticle(array $overrides = []): Article
    {
        return Article::create(array_merge([
            'locale' => 'en',
            'title' => 'Guard Passing Basics',
            'slug' => 'guard-passing-basics-'.uniqid(),
            'category' => 'Technique',
            'body' => '<p>Guard passing is a fundamental BJJ skill.</p>',
            'author_name' => 'Ehsan',
            'status' => 'draft',
        ], $overrides));
    }

    private function makePage(array $overrides = []): Page
    {
        return Page::create(array_merge([
            'locale' => 'en',
            'title' => 'Privacy Policy',
            'slug' => 'privacy-policy-'.uniqid(),
            'body' => '<p>Some page content.</p>',
            'status' => 'draft',
        ], $overrides));
    }

    private function fakeAnthropicText(string $text): void
    {
        config(['services.anthropic.key' => 'test-key']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['type' => 'text', 'text' => $text]],
            ], 200),
        ]);
    }

    private function makePanel(Article|Page $record): AiAssistantPanel
    {
        $panel = new AiAssistantPanel;
        $panel->record = $record;
        $panel->recordType = $record instanceof Article ? 'Article' : 'Page';

        return $panel;
    }

    // ============ DiffService ============

    public function test_diff_words_marks_identical_text_as_unchanged(): void
    {
        $diff = app(DiffService::class)->diffWords('Guard Passing Basics', 'Guard Passing Basics');

        $this->assertCount(1, $diff);
        $this->assertSame('same', $diff[0]['type']);
        $this->assertSame('Guard Passing Basics', $diff[0]['text']);
    }

    public function test_diff_words_detects_additions_and_removals(): void
    {
        $diff = app(DiffService::class)->diffWords('Old Title Here', 'New Title Here');

        $types = collect($diff)->pluck('type')->all();

        $this->assertContains('del', $types);
        $this->assertContains('add', $types);
        $this->assertContains('same', $types);

        $del = collect($diff)->firstWhere('type', 'del');
        $add = collect($diff)->firstWhere('type', 'add');

        $this->assertStringContainsString('Old', $del['text']);
        $this->assertStringContainsString('New', $add['text']);
    }

    public function test_diff_words_handles_blank_old_value_as_pure_addition(): void
    {
        $diff = app(DiffService::class)->diffWords(null, 'Brand New Text');

        $this->assertCount(1, $diff);
        $this->assertSame('add', $diff[0]['type']);
        $this->assertSame('Brand New Text', $diff[0]['text']);
    }

    public function test_diff_words_strips_html_tags_before_diffing(): void
    {
        $diff = app(DiffService::class)->diffWords('<p>Hello world</p>', 'Hello world');

        $this->assertCount(1, $diff);
        $this->assertSame('same', $diff[0]['type']);
    }

    // ============ ContentReviewService::scoreCard ============

    public function test_score_card_returns_all_six_categories_and_an_overall_average(): void
    {
        $article = $this->makeArticle();

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertArrayHasKey('overall', $card);
        $this->assertArrayHasKey('categories', $card);
        $this->assertEqualsCanonicalizing(
            ['seo', 'readability', 'content_quality', 'internal_linking', 'media_optimization', 'schema'],
            array_keys($card['categories'])
        );

        $average = (int) round(collect($card['categories'])->avg(fn (array $c) => $c['score']));
        $this->assertSame($average, $card['overall']);
    }

    public function test_score_card_seo_category_rewards_filled_seo_fields(): void
    {
        $bare = $this->makeArticle();
        $filled = $this->makeArticle([
            'seo_title' => 'A Great SEO Title',
            'meta_description' => str_repeat('a', 60),
            'og_title' => 'OG Title',
            'og_description' => 'OG Description',
        ]);

        $card = app(ContentReviewService::class)->scoreCard($filled);
        $bareCard = app(ContentReviewService::class)->scoreCard($bare);

        $this->assertSame(100, $card['categories']['seo']['score']);
        $this->assertSame(0, $bareCard['categories']['seo']['score']);
        $this->assertNotEmpty($bareCard['categories']['seo']['issues']);
    }

    public function test_score_card_schema_is_perfect_for_articles_and_capped_for_pages(): void
    {
        $article = $this->makeArticle();
        $page = $this->makePage();

        $articleCard = app(ContentReviewService::class)->scoreCard($article);
        $pageCard = app(ContentReviewService::class)->scoreCard($page);

        $this->assertSame(100, $articleCard['categories']['schema']['score']);
        $this->assertSame(40, $pageCard['categories']['schema']['score']);
        $this->assertNotEmpty($pageCard['categories']['schema']['issues']);
    }

    public function test_score_card_internal_linking_flags_an_orphan_with_no_inbound_or_outbound_links(): void
    {
        $article = $this->makeArticle(['body' => '<p>No links in this body at all.</p>']);

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(20, $card['categories']['internal_linking']['score']);
        $this->assertCount(2, $card['categories']['internal_linking']['issues']);
    }

    public function test_score_card_media_optimization_reflects_missing_featured_image(): void
    {
        $article = $this->makeArticle();

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(50, $card['categories']['media_optimization']['score']);
        $this->assertSame(['No featured image set.'], $card['categories']['media_optimization']['issues']);
    }

    public function test_score_card_media_optimization_uses_dam_warnings_when_a_media_row_exists(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/guard.jpg']);
        Media::create([
            'original_name' => 'guard.jpg', 'disk' => 'public', 'disk_path' => 'articles/guard.jpg',
            'url' => '/storage/articles/guard.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg',
            'size' => 1000, 'alt_text' => 'A student passing the guard',
        ]);

        $card = app(ContentReviewService::class)->scoreCard($article);

        $this->assertSame(100, $card['categories']['media_optimization']['score']);
        $this->assertSame([], $card['categories']['media_optimization']['issues']);
    }

    // ============ body field (Phase R3) ============

    public function test_body_field_is_registered_with_edit_modes_only_and_no_generate_mode(): void
    {
        $definition = ActionRegistry::for('body');

        $this->assertSame(['Article', 'Page'], $definition['applicable_to']);
        $this->assertEqualsCanonicalizing(['improve', 'rewrite', 'expand', 'shorten', 'simplify'], $definition['modes']);
        $this->assertNotContains('generate', $definition['modes']);
        $this->assertSame('html', $definition['response_shape']);
    }

    public function test_generate_for_body_field_returns_clean_html_and_strips_code_fences(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText("```html\n<p>Improved paragraph.</p>\n```");

        $outcome = app(ContentAssistantService::class)->generate($article, 'body', 'improve');

        $this->assertSame('<p>Improved paragraph.</p>', $outcome['result']);
    }

    // ============ Quick Actions / Optimize Entire Article (Phase R3) ============

    public function test_quick_seo_only_queues_generations_for_seo_og_and_slug_fields_only(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickSeoOnly();

        $fields = AiGeneration::where('content_id', $article->id)->pluck('field')->sort()->values()->all();

        $this->assertSame(['meta_description', 'og_description', 'og_title', 'seo_title', 'slug'], $fields);
        Bus::assertDispatched(RunAiContentGeneration::class, 5);
    }

    public function test_quick_faq_only_queues_only_the_faq_field(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickFaqOnly();

        $this->assertSame(['faq'], AiGeneration::where('content_id', $article->id)->pluck('field')->all());
    }

    public function test_quick_body_action_queues_the_body_field_with_the_requested_mode(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->quickBodyAction('shorten');

        $generation = AiGeneration::where('content_id', $article->id)->sole();

        $this->assertSame('body', $generation->field);
        $this->assertSame('shorten', $generation->mode);
    }

    public function test_optimize_entire_article_queues_every_generate_mode_field_except_the_review_summary_and_body(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->optimizeEntireArticle();

        $fields = AiGeneration::where('content_id', $article->id)->pluck('field')->all();

        $this->assertNotContains('content_review_summary', $fields);
        $this->assertNotContains('body', $fields);
        $this->assertContains('seo_title', $fields);
        $this->assertContains('faq', $fields);
        $this->assertContains('internal_links', $fields);

        $expectedCount = collect(ActionRegistry::applicableTo('Article'))
            ->filter(fn (array $d, string $key) => $key !== 'content_review_summary' && in_array('generate', $d['modes'], true))
            ->count();

        $this->assertCount($expectedCount, $fields);
        Bus::assertDispatched(RunAiContentGeneration::class, $expectedCount);
    }

    public function test_optimize_entire_article_never_queues_the_body_field_for_pages_either(): void
    {
        Bus::fake();

        $page = $this->makePage();
        $this->makePanel($page)->optimizeEntireArticle();

        $this->assertNotContains('body', AiGeneration::where('content_id', $page->id)->pluck('field')->all());
    }

    public function test_generation_progress_reports_done_versus_total_within_the_recent_batch(): void
    {
        $article = $this->makeArticle();

        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'seo_title', 'mode' => 'generate', 'status' => 'completed']);
        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'meta_description', 'mode' => 'generate', 'status' => 'queued']);
        AiGeneration::create(['content_type' => 'Article', 'content_id' => $article->id, 'field' => 'og_title', 'mode' => 'generate', 'status' => 'processing']);

        $this->assertSame('1 of 3 done', $this->makePanel($article)->generationProgress);
    }

    public function test_generation_progress_is_null_when_nothing_is_pending(): void
    {
        $article = $this->makeArticle();

        $this->assertNull($this->makePanel($article)->generationProgress);
    }

    // ============ Media::forRecord (Phase R4) ============

    public function test_media_for_record_returns_null_without_an_image_path(): void
    {
        $article = $this->makeArticle();

        $this->assertNull(Media::forRecord($article));
    }

    public function test_media_for_record_finds_the_matching_media_row(): void
    {
        $article = $this->makeArticle(['image_path' => 'articles/guard.jpg']);
        $media = Media::create([
            'original_name' => 'guard.jpg', 'disk' => 'public', 'disk_path' => 'articles/guard.jpg',
            'url' => '/storage/articles/guard.jpg', 'type' => 'image', 'mime_type' => 'image/jpeg', 'size' => 1000,
        ]);

        $this->assertTrue(Media::forRecord($article)->is($media));
    }

    // ============ ContentAssistantService::classifyIntent (Phase R4, AI Chat) ============

    public function test_classify_intent_returns_a_valid_action_for_a_known_field_and_mode(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            'intent' => 'action', 'field' => 'faq', 'mode' => 'generate', 'reply' => 'Generating 5 FAQs now.',
        ]));

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Generate 5 FAQs');

        $this->assertSame('action', $result['intent']);
        $this->assertSame('faq', $result['field']);
        $this->assertSame('generate', $result['mode']);
        $this->assertSame('Generating 5 FAQs now.', $result['reply']);
    }

    public function test_classify_intent_falls_back_to_chat_when_the_field_or_mode_is_invalid(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            'intent' => 'action', 'field' => 'not_a_real_field', 'mode' => 'generate', 'reply' => 'Sure.',
        ]));

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Do something weird');

        $this->assertSame('chat', $result['intent']);
        $this->assertNull($result['field']);
        $this->assertNull($result['mode']);
    }

    public function test_classify_intent_rejects_a_mode_not_allowed_for_that_field(): void
    {
        $article = $this->makeArticle();
        // «faq» فقط generate/improve/expand دارد — shorten برایش مجاز نیست
        $this->fakeAnthropicText(json_encode([
            'intent' => 'action', 'field' => 'faq', 'mode' => 'shorten', 'reply' => 'Sure.',
        ]));

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Shorten the FAQ');

        $this->assertSame('chat', $result['intent']);
    }

    public function test_classify_intent_returns_translate_with_a_valid_target_locale(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            'intent' => 'translate', 'target_locale' => 'tr', 'reply' => 'Preparing a Turkish draft.',
        ]));

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Translate to Turkish');

        $this->assertSame('translate', $result['intent']);
        $this->assertSame('tr', $result['target_locale']);
    }

    public function test_classify_intent_falls_back_to_chat_when_translate_has_no_target_locale(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode(['intent' => 'translate', 'reply' => 'Translate to what?']));

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Translate this');

        $this->assertSame('chat', $result['intent']);
    }

    public function test_classify_intent_falls_back_to_chat_on_malformed_json(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('Sure, happy to help with that!');

        $result = app(ContentAssistantService::class)->classifyIntent($article, 'Hello there');

        $this->assertSame('chat', $result['intent']);
        $this->assertSame('Sure, happy to help with that!', $result['reply']);
    }

    // ============ ProcessAiChatMessage job (Phase R4) ============

    public function test_process_chat_message_queues_a_generation_and_links_it_to_the_assistant_reply(): void
    {
        Bus::fake([RunAiContentGeneration::class]);

        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            'intent' => 'action', 'field' => 'seo_title', 'mode' => 'generate', 'reply' => 'On it!',
        ]));

        $userMessage = AiChatMessage::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'role' => 'user', 'message' => 'Generate an SEO title',
        ]);

        (new ProcessAiChatMessage('Article', $article->id, $userMessage->id))->handle(app(ContentAssistantService::class));

        $generation = AiGeneration::where('content_id', $article->id)->sole();
        $this->assertSame('seo_title', $generation->field);

        $assistantMessage = AiChatMessage::where('role', 'assistant')->sole();
        $this->assertSame('On it!', $assistantMessage->message);
        $this->assertSame($generation->id, $assistantMessage->related_generation_id);

        Bus::assertDispatched(RunAiContentGeneration::class);
    }

    public function test_process_chat_message_replies_without_queuing_anything_for_plain_chat(): void
    {
        Bus::fake([RunAiContentGeneration::class]);

        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode(['intent' => 'chat', 'reply' => 'This article looks great!']));

        $userMessage = AiChatMessage::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'role' => 'user', 'message' => 'How does this look?',
        ]);

        (new ProcessAiChatMessage('Article', $article->id, $userMessage->id))->handle(app(ContentAssistantService::class));

        $this->assertSame(0, AiGeneration::where('content_id', $article->id)->count());

        $assistantMessage = AiChatMessage::where('role', 'assistant')->sole();
        $this->assertSame('This article looks great!', $assistantMessage->message);
        $this->assertNull($assistantMessage->related_generation_id);

        Bus::assertNotDispatched(RunAiContentGeneration::class);
    }

    public function test_process_chat_message_does_nothing_when_the_user_message_was_deleted(): void
    {
        $article = $this->makeArticle();

        (new ProcessAiChatMessage('Article', $article->id, 999999))->handle(app(ContentAssistantService::class));

        $this->assertSame(0, AiChatMessage::count());
    }

    // ============ AiAssistantPanel chat wiring (Phase R4) ============

    public function test_send_chat_message_creates_a_user_message_and_dispatches_the_job(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $panel = $this->makePanel($article);
        $panel->chatInput = 'Improve the introduction';
        $panel->sendChatMessage();

        $message = AiChatMessage::sole();
        $this->assertSame('user', $message->role);
        $this->assertSame('Improve the introduction', $message->message);
        $this->assertSame('', $panel->chatInput);

        Bus::assertDispatched(ProcessAiChatMessage::class);
    }

    public function test_send_chat_message_ignores_blank_input(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $panel = $this->makePanel($article);
        $panel->chatInput = '   ';
        $panel->sendChatMessage();

        $this->assertSame(0, AiChatMessage::count());
        Bus::assertNotDispatched(ProcessAiChatMessage::class);
    }

    public function test_is_chat_pending_is_true_only_when_the_last_message_is_from_the_user(): void
    {
        $article = $this->makeArticle();

        $this->assertFalse($this->makePanel($article)->isChatPending);

        AiChatMessage::create(['content_type' => 'Article', 'content_id' => $article->id, 'role' => 'user', 'message' => 'Hi']);
        $this->assertTrue($this->makePanel($article)->isChatPending);

        AiChatMessage::create(['content_type' => 'Article', 'content_id' => $article->id, 'role' => 'assistant', 'message' => 'Hello!']);
        $this->assertFalse($this->makePanel($article)->isChatPending);
    }

    // ============ ContentAssistantService::buildTranslationPayload (Phase R5, Translate) ============

    public function test_build_translation_payload_for_an_article_includes_excerpt_and_faqs(): void
    {
        $article = $this->makeArticle([
            'excerpt' => 'A short summary.',
            'faqs' => [['question' => 'Q1', 'answer' => 'A1']],
        ]);
        $this->fakeAnthropicText(json_encode([
            'title' => 'Temel Muhafaza Geçişi', 'body' => '<p>Muhafaza geçişi temel bir teknik.</p>',
            'excerpt' => 'Kısa bir özet.', 'faqs' => [['question' => 'S1', 'answer' => 'C1']],
        ]));

        $result = app(ContentAssistantService::class)->buildTranslationPayload($article, 'tr');

        $this->assertSame('Temel Muhafaza Geçişi', $result['title']);
        $this->assertSame('Kısa bir özet.', $result['excerpt']);
        $this->assertSame([['question' => 'S1', 'answer' => 'C1']], $result['faqs']);
    }

    public function test_build_translation_payload_for_a_page_never_includes_excerpt_or_faqs(): void
    {
        $page = $this->makePage();
        $this->fakeAnthropicText(json_encode([
            'title' => 'Gizlilik Politikası', 'body' => '<p>İçerik.</p>',
            'excerpt' => 'This should be ignored', 'faqs' => [['question' => 'Q', 'answer' => 'A']],
        ]));

        $result = app(ContentAssistantService::class)->buildTranslationPayload($page, 'tr');

        $this->assertSame('Gizlilik Politikası', $result['title']);
        $this->assertNull($result['excerpt']);
        $this->assertNull($result['faqs']);
    }

    public function test_build_translation_payload_throws_on_a_malformed_response(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('not json at all');

        $this->expectException(\RuntimeException::class);

        app(ContentAssistantService::class)->buildTranslationPayload($article, 'tr');
    }

    // ============ TranslateArticleDraft job (Phase R5) ============

    public function test_translate_article_draft_creates_a_linked_draft_article(): void
    {
        $article = $this->makeArticle(['status' => 'published']);
        $this->fakeAnthropicText(json_encode([
            'title' => 'Translated Title', 'body' => '<p>Translated body.</p>', 'excerpt' => null, 'faqs' => [],
        ]));

        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'translate', 'mode' => 'tr', 'status' => 'queued',
        ]);

        (new TranslateArticleDraft('Article', $article->id, 'tr', $generation->id))
            ->handle(app(ContentAssistantService::class), app(ArticleImportService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status);

        $translated = Article::find($generation->result['id']);
        $this->assertNotNull($translated);
        $this->assertSame('tr', $translated->locale);
        $this->assertSame('draft', $translated->status);
        $this->assertSame($article->id, $translated->translation_of);
        $this->assertSame('Translated Title', $translated->title);
    }

    public function test_translate_article_draft_creates_a_linked_draft_page(): void
    {
        $page = $this->makePage(['status' => 'published']);
        $this->fakeAnthropicText(json_encode(['title' => 'Çevrilmiş Başlık', 'body' => '<p>Çevrilmiş içerik.</p>']));

        $generation = AiGeneration::create([
            'content_type' => 'Page', 'content_id' => $page->id, 'field' => 'translate', 'mode' => 'tr', 'status' => 'queued',
        ]);

        (new TranslateArticleDraft('Page', $page->id, 'tr', $generation->id))
            ->handle(app(ContentAssistantService::class), app(ArticleImportService::class));

        $generation->refresh();
        $this->assertSame('completed', $generation->status);

        $translated = Page::find($generation->result['id']);
        $this->assertNotNull($translated);
        $this->assertSame('tr', $translated->locale);
        $this->assertSame('draft', $translated->status);
        $this->assertSame($page->id, $translated->translation_of);
    }

    public function test_translate_article_draft_fails_gracefully_when_the_ai_response_is_unusable(): void
    {
        $article = $this->makeArticle();
        $this->fakeAnthropicText('not json');

        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'field' => 'translate', 'mode' => 'tr', 'status' => 'queued',
        ]);

        (new TranslateArticleDraft('Article', $article->id, 'tr', $generation->id))
            ->handle(app(ContentAssistantService::class), app(ArticleImportService::class));

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertNotNull($generation->error);
        $this->assertSame(1, Article::count());
    }

    public function test_translate_article_draft_fails_gracefully_when_the_source_no_longer_exists(): void
    {
        $generation = AiGeneration::create([
            'content_type' => 'Article', 'content_id' => 999999, 'field' => 'translate', 'mode' => 'tr', 'status' => 'queued',
        ]);

        (new TranslateArticleDraft('Article', 999999, 'tr', $generation->id))
            ->handle(app(ContentAssistantService::class), app(ArticleImportService::class));

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertStringContainsString('no longer exists', $generation->error);
    }

    // ============ AiAssistantPanel::translate() + chat translate intent (Phase R5) ============

    public function test_panel_translate_queues_a_generation_and_dispatches_the_job(): void
    {
        Bus::fake();

        $article = $this->makeArticle();
        $this->makePanel($article)->translate('tr');

        $generation = AiGeneration::where('content_id', $article->id)->sole();
        $this->assertSame('translate', $generation->field);
        $this->assertSame('tr', $generation->mode);

        Bus::assertDispatched(TranslateArticleDraft::class);
    }

    public function test_process_chat_message_dispatches_translate_job_for_translate_intent(): void
    {
        Bus::fake([TranslateArticleDraft::class]);

        $article = $this->makeArticle();
        $this->fakeAnthropicText(json_encode([
            'intent' => 'translate', 'target_locale' => 'tr', 'reply' => 'Preparing a Turkish draft now.',
        ]));

        $userMessage = AiChatMessage::create([
            'content_type' => 'Article', 'content_id' => $article->id, 'role' => 'user', 'message' => 'Translate to Turkish',
        ]);

        (new ProcessAiChatMessage('Article', $article->id, $userMessage->id))->handle(app(ContentAssistantService::class));

        $generation = AiGeneration::where('content_id', $article->id)->sole();
        $this->assertSame('translate', $generation->field);
        $this->assertSame('tr', $generation->mode);

        $assistantMessage = AiChatMessage::where('role', 'assistant')->sole();
        $this->assertSame($generation->id, $assistantMessage->related_generation_id);

        Bus::assertDispatched(TranslateArticleDraft::class);
    }
}
