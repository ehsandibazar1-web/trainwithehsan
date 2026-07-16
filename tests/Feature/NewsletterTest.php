<?php

namespace Tests\Feature;

use App\Mail\NewsletterVerificationMail;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'visitor@example.com',
            'locale' => 'en',
            'website' => '',
            '_nl_ts' => Crypt::encryptString((string) now()->subSeconds(10)->timestamp),
        ], $overrides);
    }

    public function test_subscribe_creates_pending_subscriber_and_sends_verification(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload())
            ->assertOk()
            ->assertJson(['ok' => true, 'message' => __('newsletter.subscribed', [], 'en')]);

        $sub = NewsletterSubscriber::where('email', 'visitor@example.com')->first();
        $this->assertNotNull($sub);
        $this->assertFalse($sub->isVerified());
        $this->assertSame('subscribed', $sub->status);
        $this->assertSame('footer', $sub->source);
        $this->assertNotEmpty($sub->verification_token);
        $this->assertNotEmpty($sub->unsubscribe_token);
        $this->assertNotNull($sub->ip_address);

        Mail::assertSent(NewsletterVerificationMail::class, 1);
    }

    public function test_turkish_locale_gets_turkish_message(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload(['locale' => 'tr']))
            ->assertOk()
            ->assertJson(['message' => __('newsletter.subscribed', [], 'tr')]);

        $this->assertSame('tr', NewsletterSubscriber::first()->locale);
    }

    public function test_invalid_email_is_rejected_with_translated_message(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload(['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJson(['ok' => false, 'message' => __('newsletter.invalid_email', [], 'en')]);

        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_honeypot_fills_are_silently_dropped_and_logged(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload(['website' => 'http://spam.example']))
            ->assertOk()
            ->assertJson(['ok' => true]); // پاسخ موفق الکی برای بات

        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_too_fast_submission_is_treated_as_bot(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload([
            '_nl_ts' => Crypt::encryptString((string) now()->timestamp),
        ]))->assertOk();

        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_tampered_time_gate_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/newsletter/subscribe', $this->validPayload(['_nl_ts' => 'tampered']))
            ->assertOk();

        $this->assertSame(0, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_duplicate_verified_subscriber_is_not_recreated(): void
    {
        Mail::fake();

        NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'en',
            'verified_at' => now(), 'verification_sent_at' => now()->subDay(),
        ]);

        $this->postJson('/newsletter/subscribe', $this->validPayload())
            ->assertOk()
            ->assertJson(['message' => __('newsletter.already_subscribed', [], 'en')]);

        $this->assertSame(1, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_unverified_duplicate_gets_verification_resent_after_cooldown(): void
    {
        Mail::fake();

        NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'en',
            'verification_sent_at' => now()->subMinutes(10),
        ]);

        $this->postJson('/newsletter/subscribe', $this->validPayload())
            ->assertOk()
            ->assertJson(['message' => __('newsletter.resent', [], 'en')]);

        $this->assertSame(1, NewsletterSubscriber::count());
        Mail::assertSent(NewsletterVerificationMail::class, 1);
    }

    public function test_verification_link_verifies_subscriber(): void
    {
        $sub = NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'en',
            'verification_sent_at' => now(),
        ]);

        $this->get('/newsletter/verify/'.$sub->verification_token)
            ->assertOk()
            ->assertSee(__('newsletter.verify_success_title', [], 'en'));

        $this->assertTrue($sub->fresh()->isVerified());
    }

    public function test_expired_verification_link_shows_expired_page(): void
    {
        $sub = NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'en',
            'verification_sent_at' => now()->subHours(25),
        ]);

        $this->get('/newsletter/verify/'.$sub->verification_token)
            ->assertStatus(410)
            ->assertSee(__('newsletter.verify_expired_title', [], 'en'));

        $this->assertFalse($sub->fresh()->isVerified());
    }

    public function test_invalid_verification_token_404s(): void
    {
        $this->get('/newsletter/verify/'.str_repeat('x', 64))->assertNotFound();
    }

    public function test_one_click_unsubscribe_works_without_login(): void
    {
        $sub = NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'tr',
            'verified_at' => now(), 'verification_sent_at' => now(),
        ]);

        $this->get('/newsletter/unsubscribe/'.$sub->unsubscribe_token)
            ->assertOk()
            ->assertSee(__('newsletter.unsubscribe_title', [], 'tr'));

        $sub->refresh();
        $this->assertSame('unsubscribed', $sub->status);
        $this->assertNotNull($sub->unsubscribed_at);
    }

    public function test_rate_limiting_kicks_in(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/newsletter/subscribe', $this->validPayload(['email' => "v{$i}@example.com"]));
        }

        $this->postJson('/newsletter/subscribe', $this->validPayload(['email' => 'v6@example.com']))
            ->assertStatus(429);
    }

    public function test_footer_form_is_wired_on_both_locales(): void
    {
        foreach (['/' => 'en', '/tr' => 'tr'] as $url => $locale) {
            $this->get($url)
                ->assertOk()
                ->assertSee('js-newsletter-form')
                ->assertSee('name="website"', false)
                ->assertSee('name="_nl_ts"', false)
                ->assertSee('value="'.$locale.'"', false);
        }
    }

    public function test_admin_newsletter_resource_renders(): void
    {
        NewsletterSubscriber::create([
            'email' => 'visitor@example.com', 'locale' => 'en',
            'verification_sent_at' => now(),
        ]);

        $user = User::factory()->create(['email' => 'ehsan.dibazar1@gmail.com']);

        $this->actingAs($user)->get('/admin/newsletter-subscribers')
            ->assertOk()
            ->assertSee('visitor@example.com');
    }
}
