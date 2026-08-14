<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google Search Console reported the site indexed under 4 separate host variations
 * (https/non-www canonical, plus www/http duplicates splitting ranking signals) — see the
 * 2026-08-14 "Canonical Host Consolidation Directive". Two layers fix it: public/.htaccess
 * 301-redirects any non-canonical host to APP_URL's host (Apache-level, not testable from
 * PHPUnit), and AppServiceProvider::boot() calls URL::forceRootUrl(config('app.url')) so every
 * URL Laravel itself generates (canonical, og:url, hreflang, sitemap, RSS — all built via the
 * `url()` helper throughout this codebase) always uses the canonical host, never whatever host
 * header the current request happened to arrive on. Before this fix, url()->current()/url('/x')
 * mirrored the request's own host — confirmed by a direct UrlGenerator test — so a visitor/bot
 * reaching the site via www would get a self-referencing www canonical, reinforcing the
 * duplicate instead of consolidating it.
 */
class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_tag_ignores_the_requests_own_host_and_uses_app_url(): void
    {
        // میزبانِ درخواست عمداً چیزی غیر از APP_URL (که در .env این محیط http://localhost است)
        $response = $this->get('http://www.not-the-canonical-host.test/');

        $response->assertOk();
        $response->assertSee('rel="canonical" href="'.url('/').'"', false);
        $response->assertDontSee('not-the-canonical-host.test', false);
    }

    public function test_og_url_ignores_the_requests_own_host_too(): void
    {
        $response = $this->get('http://www.not-the-canonical-host.test/');

        $response->assertSee('property="og:url" content="'.url('/').'"', false);
    }

    public function test_url_helper_never_reflects_the_current_requests_host(): void
    {
        $this->get('http://www.not-the-canonical-host.test/blog/whatever');

        // این خودِ رفتارِ URL::forceRootUrl را مستقیم چک می‌کند — بدونِ آن، url('/x') میزبانِ
        // درخواستِ فعلی را برمی‌گرداند، نه config('app.url')
        $this->assertSame(rtrim(config('app.url'), '/').'/x', url('/x'));
        $this->assertStringStartsWith(config('app.url'), url()->current());
    }
}
