<?php

namespace Tests\Feature;

use App\Jobs\ImportAiArticle;
use App\Models\ApiToken;
use App\Models\Article;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AiImportApiTest extends TestCase
{
    use RefreshDatabase;

    private string $plainToken;

    private ApiToken $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plainToken = ApiToken::generatePlainToken();
        $this->token = ApiToken::create([
            'name' => 'Claude',
            'token_hash' => hash('sha256', $this->plainToken),
            'prefix' => substr($this->plainToken, 0, 12),
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'language' => 'en',
            'title' => 'API Imported Article',
            'content' => '<p>Body from the API.</p>',
            'excerpt' => 'API excerpt.',
            'provider' => 'claude',
        ], $overrides);
    }

    private function apiPost(string $uri, array $payload)
    {
        return $this->withToken($this->plainToken)->postJson($uri, $payload);
    }

    // ----------------------------------------------------------- authentication

    public function test_request_without_token_is_rejected(): void
    {
        $this->postJson('/api/ai-import', $this->payload())->assertStatus(401);
        $this->assertSame(0, Article::count());
        $this->assertSame(0, ImportLog::count());
    }

    public function test_request_with_invalid_token_is_rejected(): void
    {
        $this->withToken('aiimp_wrong-token')
            ->postJson('/api/ai-import', $this->payload())
            ->assertStatus(401);
    }

    public function test_token_with_no_expiry_still_works(): void
    {
        // expires_at پیش‌فرض null است — یعنی «هرگز منقضی نمی‌شود»؛ باید دقیقاً رفتار قبلی
        // (بدون این ستون) را حفظ کند
        $this->assertNull($this->token->expires_at);

        $this->apiPost('/api/ai-import/validate', $this->payload())->assertStatus(200);
    }

    public function test_token_with_future_expiry_still_works(): void
    {
        $this->token->update(['expires_at' => now()->addDay()]);

        $this->apiPost('/api/ai-import/validate', $this->payload())->assertStatus(200);
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->token->update(['expires_at' => now()->subMinute()]);

        $this->apiPost('/api/ai-import/validate', $this->payload())->assertStatus(401);
    }

    public function test_token_last_used_at_is_recorded(): void
    {
        $this->assertNull($this->token->last_used_at);

        $this->apiPost('/api/ai-import/validate', $this->payload());

        $this->assertNotNull($this->token->fresh()->last_used_at);
    }

    // ----------------------------------------------------- forced draft policy

    public function test_import_is_always_saved_as_draft_even_when_payload_says_published(): void
    {
        $response = $this->apiPost('/api/ai-import', $this->payload([
            'publish_status' => 'published',
        ]));

        $response->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('article.status', 'draft');

        $article = Article::first();
        $this->assertSame('draft', $article->status);

        // پیش‌نویس است، پس در هیچ فهرست عمومی دیده نمی‌شود — انتشار فقط با تأیید مدیر
        $this->assertSame(0, Article::published()->count());
    }

    public function test_response_contains_signed_preview_url_and_log_reference(): void
    {
        $response = $this->apiPost('/api/ai-import', $this->payload());

        $preview = $response->json('preview_url');
        $this->assertStringContainsString('/preview/article/', $preview);
        $this->assertStringContainsString('signature=', $preview);

        // لینک پیش‌نمایش امضاشده واقعاً کار می‌کند (سیستم پیش‌نمایش موجود)
        $this->get($preview)->assertOk();

        $this->assertSame($response->json('import_log_id'), ImportLog::first()->id);
    }

    public function test_import_log_records_api_source_and_token(): void
    {
        $this->apiPost('/api/ai-import', $this->payload());

        $log = ImportLog::first();
        $this->assertSame('api', $log->source);
        $this->assertSame($this->token->id, $log->api_token_id);
        $this->assertNull($log->user_id);
        $this->assertSame('claude', $log->ai_provider);
    }

    public function test_imported_draft_appears_in_draft_queue(): void
    {
        $this->apiPost('/api/ai-import', $this->payload(['title' => 'Waiting For Approval']));

        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($owner)
            ->get('/admin/draft-queue')
            ->assertOk()
            ->assertSee('Waiting For Approval');
    }

    // ---------------------------------------------------------------- validation

    public function test_invalid_payload_returns_422_and_failed_log(): void
    {
        $this->apiPost('/api/ai-import', ['language' => 'en'])
            ->assertStatus(422)
            ->assertJsonPath('ok', false);

        $this->assertSame(0, Article::count());
        $this->assertSame('failed', ImportLog::first()->status);
    }

    public function test_validate_endpoint_is_a_dry_run(): void
    {
        $this->apiPost('/api/ai-import/validate', $this->payload())
            ->assertOk()
            ->assertJsonPath('valid', true);

        $this->apiPost('/api/ai-import/validate', ['language' => 'en'])
            ->assertOk()
            ->assertJsonPath('valid', false);

        // اعتبارسنجی خشک: نه مقاله، نه لاگ
        $this->assertSame(0, Article::count());
        $this->assertSame(0, ImportLog::count());
    }

    public function test_duplicate_slug_is_rejected_through_api(): void
    {
        Article::create([
            'locale' => 'en', 'title' => 'Existing', 'slug' => 'api-imported-article',
            'body' => 'x', 'status' => 'published', 'published_at' => now(),
        ]);

        $this->apiPost('/api/ai-import', $this->payload())
            ->assertStatus(422)
            ->assertJsonFragment(['ok' => false]);
    }

    // -------------------------------------------------------------------- queue

    public function test_queued_import_dispatches_job(): void
    {
        Queue::fake();

        $this->apiPost('/api/ai-import', $this->payload(['queue' => true]))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        Queue::assertPushed(ImportAiArticle::class, 1);
        $this->assertSame(0, Article::count());
    }

    public function test_queued_import_creates_draft_when_job_runs(): void
    {
        // QUEUE_CONNECTION=sync در تست‌ها — dispatch بلافاصله اجرا می‌شود
        $this->apiPost('/api/ai-import', $this->payload(['queue' => true]))
            ->assertStatus(202);

        $article = Article::first();
        $this->assertNotNull($article);
        $this->assertSame('draft', $article->status);
        $this->assertSame('api', ImportLog::first()->source);
    }

    // ------------------------------------------------------------- rate limiting

    public function test_rate_limiting_kicks_in_per_token(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->apiPost('/api/ai-import/validate', $this->payload());
        }

        $this->apiPost('/api/ai-import/validate', $this->payload())->assertStatus(429);
    }

    public function test_repeated_invalid_token_requests_are_throttled_by_ip(): void
    {
        // میان‌افزار احراز هویت روی توکنِ نامعتبر کوتاه‌مدار می‌شود، پس محدودیتِ per-token
        // هرگز اجرا نمی‌شود — این تست مطمئن می‌شود که با این حال یک سقفِ مستقلِ بر اساسِ IP
        // جلوی سیلِ نامحدودِ درخواست با توکنِ نامعتبر را می‌گیرد. عمداً به مرز دقیق سقف تکیه
        // نمی‌کند (فقط مطمئن می‌شود که بعد از تعداد زیادی درخواست، در نهایت 429 دیده می‌شود).
        $sawUnauthorized = false;
        $sawThrottled = false;

        for ($i = 0; $i < 80; $i++) {
            $status = $this->withToken('aiimp_wrong-token')
                ->postJson('/api/ai-import/validate', $this->payload())
                ->getStatusCode();

            if ($status === 401) {
                $sawUnauthorized = true;
            } elseif ($status === 429) {
                $sawThrottled = true;
                break;
            } else {
                $this->fail("Unexpected status code {$status} on attempt {$i}.");
            }
        }

        $this->assertTrue($sawUnauthorized, 'Expected at least one 401 before throttling kicked in.');
        $this->assertTrue($sawThrottled, 'Expected repeated invalid-token requests to eventually be throttled (429).');
    }

    // ---------------------------------------------------------------- admin UI

    public function test_api_tokens_resource_renders(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($owner)
            ->get('/admin/api-tokens')
            ->assertOk()
            ->assertSee('API Tokens')
            ->assertSee('Claude');
    }

    public function test_api_token_create_form_renders_with_optional_expiry_field(): void
    {
        $owner = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($owner)
            ->get('/admin/api-tokens/create')
            ->assertOk()
            ->assertSee('Expires at');
    }
}
