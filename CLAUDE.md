# CLAUDE.md — Train with Ehsan

This file is the permanent knowledge base for this repository. Read it fully before making changes. It reflects the actual, verified state of the codebase — not aspirations. Sections marked **(Convention to adopt)** describe practices that do not yet exist in the repo's history and should be introduced going forward; everything else describes what is already true today.

## 1. Project Overview

**Train with Ehsan** is the marketing and content website for Ehsan Dibazar's self-defense / Brazilian Jiu-Jitsu ("Martial Intelligence") training business in Istanbul, served at `trainwithehsan.com`. The public site is a bilingual (English + Turkish) content site whose visual design is being built to pixel-match an existing live reference site (`ehsandibazar.com`) — the git history shows extensive iteration ("Match footer exactly to ehsandibazar.com", "Fix About section image position to match ehsandibazar.com exactly", etc.). Behind the public site is a Filament v4 admin CMS that lets the owner (non-developer) manage blog articles, homepage content blocks, navigation, and a publishing calendar without touching code.

Two audiences to keep in mind on every change:
1. **Site visitors** — must get a fast, correctly-localized, SEO-crawlable page.
2. **Ehsan (the content owner)** — uses only the `/admin` Filament panel. He is not a developer. Any admin-facing change must remain usable by a non-technical person (clear labels, sane defaults, no raw JSON/HTML editing exposed to him).

## 2. Brand Identity

- `ehsandibazar.com` is the visual and UX reference for this site. When a design question arises ("how should this look/behave?"), the answer is "match the reference site" unless the user explicitly says otherwise — this is not a one-time bootstrap task, it is the standing design authority for this project.
- **Preserve the existing design language unless explicitly requested otherwise.** The current hand-rolled CSS, layout structure, color palette (`--gold:#d9bb75`, dark header/footer, etc.), and component shapes in `master.blade.php`/`master-tr.blade.php` are the result of many iterations matching the reference site pixel-by-pixel (see git history) — treat them as settled, not as a draft to improve on aesthetic taste alone.
- **SEO, performance, and usability outrank introducing new technologies.** When a new tool/library/framework would make an implementation "cleaner" but costs crawlability, load time, or simplicity, choose the boring option that protects SEO/performance/usability. This project has stayed deliberately dependency-light (see Architecture, Coding Standards) — that is a feature, not a gap to fill.
- **Never redesign UI without explicit user approval.** This includes swapping layout structure, replacing hand-rolled CSS with a component/utility system wholesale, changing the color palette, or altering established page structure — even if it would be "better practice." Propose and get sign-off first; do not treat a bug fix or feature request as license to also modernize the surrounding UI.

## 3. Architecture

- **Framework**: Laravel 13 (PHP ^8.3).
- **Admin panel**: Filament v4, mounted at `/admin` via `App\Providers\Filament\AdminPanelProvider`.
- **Rendering**: Server-rendered Blade views for the public site. No SPA framework, no Livewire on the public side (Livewire ships only as a Filament dependency). `resources/js/app.js` is a minimal vanilla JS entry point — keep it that way; do not introduce a frontend framework for the public site without discussing it first, since the whole site is intentionally simple, content-driven, and SEO-first.
- **Styling**: Tailwind CSS v4 via `@tailwindcss/vite`, but the actual public-site look is mostly hand-rolled CSS embedded in `resources/views/layouts/master.blade.php` / `master-tr.blade.php` (a large `<style>` block matching the reference site's exact CSS values, with comments like `/* مقادیر عیناً از site.min.css سایت فارسی */` — "values copied exactly from the Persian site's site.min.css"). Tailwind utility classes and the hand-rolled CSS coexist; when touching public-site visuals, match the existing hand-rolled CSS pattern in that file rather than introducing a new styling approach.
- **Persistence**: SQLite (`DB_CONNECTION=sqlite`) by default. Session, cache, and queue all use the **database** driver (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`) — there is no Redis/Memcached in actual use today even though `.env.example` lists Redis config (unused placeholder from the Laravel skeleton).
- **Scheduling**: `routes/console.php` registers `Schedule::command('articles:publish-due')->everyFiveMinutes()`. This requires the Laravel scheduler (`php artisan schedule:run`) to be wired into a real cron entry on the server — verify this exists on prod/staging; it is not automatic.
- **Activity logging**: `spatie/laravel-activitylog` (v5) logs `Article` changes (create/update/delete, dirty-only, specific fields) for display in the Filament `ActivityLogPage`.
- **No API layer**: there is no REST/JSON API, no SPA backend contract. All output is either full-page HTML (Blade) or XML (sitemap/RSS). Do not add API routes unless a real client needs them — keep the surface area minimal.

## 4. Folder Structure

```
app/
  Console/Commands/PublishDueArticles.php   Scheduled auto-publish job
  Filament/
    Resources/Articles/                     ArticleResource + Schemas/ArticleForm.php, Tables/ArticlesTable.php, Pages/
    Pages/EditorialCalendar.php              Drag-and-drop article scheduling calendar
    Pages/HomepageSettings.php               Homepage content-block editor (per locale)
    Pages/AboutPageSettings.php              About page content-block editor (hero, stats, certificates, gallery, timeline, CTA, SEO — per locale)
    Pages/MenuSettings.php                   Header nav menu editor (per locale)
    Pages/ActivityLogPage.php                Read-only activity log viewer
    Widgets/ArticleStatsOverview.php         Dashboard stats widget
  Http/Controllers/
    BlogController.php                       Home, about, blog index, blog show — EN and TR variants
    SeoController.php                        sitemap.xml, /feed, /tr/feed
    PreviewController.php                    Signed-URL preview of unpublished/scheduled articles
  Models/
    Article.php                              Bilingual article model, translation self-relation, published/locale scopes
    SiteSetting.php                          Generic key/value store — the ad hoc CMS backend for homepage/about-page/menu content
    Media.php                                 Metadata record for uploaded media (name/path/url/mime/size)
    User.php                                  Default Laravel auth user (admin login)
  Providers/Filament/AdminPanelProvider.php   Filament panel configuration
config/                                       Standard Laravel config (app, auth, cache, database, filesystems, livewire, logging, mail, queue, services, session)
database/
  migrations/                                users, cache, jobs (Laravel defaults) + articles, media, site_settings, activity_log (project-specific, dated 2026-07-xx)
  factories/, seeders/
resources/
  views/
    home.blade.php, about.blade.php, blog.blade.php, blog-post.blade.php     English public pages
    tr/home.blade.php, tr/about.blade.php, tr/blog.blade.php, tr/blog-post.blade.php   Turkish duplicates
    layouts/master.blade.php, layouts/master-tr.blade.php                    Shared page shell per locale (head/meta/CSS/header/footer)
    welcome.blade.php                        Default Laravel scaffold view — UNUSED, not routed to. Safe to delete; do not build on it.
    filament/pages/                           Custom Filament page Blade views (homepage-settings, about-page-settings, menu-settings, editorial-calendar, activity-log)
  css/app.css, js/app.js                      Vite entry points (Tailwind + minimal JS)
routes/
  web.php                                     All public + admin-adjacent routes (see Security Rules — contains two routes that must be fixed)
  console.php                                 Artisan closures + the schedule definition
public/                                       index.php, favicon, robots.txt, compiled Filament vendor assets
tests/
  Feature/ExampleTest.php, Unit/ExampleTest.php   Default Laravel scaffold tests only — NOT project-specific coverage (see Testing Strategy)
```

Key duplication to be aware of: **every public-facing view and its Turkish counterpart are separate files** (`home.blade.php` / `tr/home.blade.php`, etc.), and `routes/web.php` registers separate controller methods per locale (`home`/`homeTr`, `index`/`indexTr`, `show`/`showTr`). This is a deliberate current structure, not an oversight in progress — see "Important Project Decisions" before attempting to collapse it.

## 5. Laravel Conventions

- Controllers are thin: `BlogController` and `SeoController` build query results and pass them straight to a view or XML string — no service classes, no repositories, no form requests (there is no user-facing form submission on the public site to validate). Keep this simplicity; do not introduce a service-layer abstraction for a two-controller app.
- Query scopes live on the model (`Article::published()`, `Article::locale($locale)`), not as query builder macros or repository methods. Follow this pattern for any new reusable query logic.
- `SiteSetting` is a flat `key => value` store (e.g. `home.en.hero_title`) read with a single `WHERE key LIKE 'home.en.%'` query and reshaped into an array in `BlogController::homeSettings()`. This is intentionally schemaless so Ehsan can add/edit homepage blocks from Filament without migrations. When adding a new homepage content block, follow this same `home.{locale}.{field}` key convention — do not create a new dedicated table for homepage content.
- The same `{page}.{locale}.{field}` convention is used for the About page (`about.en.hero_name`, `about.tr.timeline`, etc.), read via `BlogController::aboutSettings()`. Repeater blocks (`stats`, `certificates`, `gallery`, `timeline`) are stored as JSON under a single key per locale (e.g. `about.en.certificates`) and decoded with `SiteSetting::getJson()`/`json_decode`, exactly like `home.en.members`. Follow this same pattern for any future page that needs a CMS-managed content block — do not create a dedicated table.
- Bilingual content is modeled as **two separate `Article` rows** (one `locale = en`, one `locale = tr`), optionally linked via `translation_of` pointing at the other row's `id`. There is no single row with `title_en`/`title_tr` columns — do not refactor toward that shape; the two-row-with-link model is intentional (it lets an article exist in only one language, and keeps the admin form simple per-locale).
- Comments in domain logic (`BlogController`, `Article`, `SeoController`, `PublishDueArticles`, `EditorialCalendar`) are written in **Farsi** by convention, explaining the *why* of non-obvious logic (e.g. the safety net in `scopePublished()` for when cron fails). Continue this convention for new domain logic in these files — do not switch to English-only comments in files that already mix Farsi, and do not add comments explaining *what* the code does when the code is self-evident.
- Formatting: `laravel/pint` is installed but uses Laravel's default preset (no `pint.json`). Run `./vendor/bin/pint` before committing PHP changes — do not hand-format against a different style.

## 6. Filament Conventions

- Filament v4 resources live under `app/Filament/Resources/{Name}/` with `Schemas/{Name}Form.php` for the form definition and `Tables/{Name}sTable.php` for the table definition, plus a `Pages/` subfolder for List/Create/Edit pages — follow `ArticleResource`'s layout exactly for any new resource.
- Custom (non-CRUD) admin screens are Filament **Pages**, not Resources — `EditorialCalendar`, `HomepageSettings`, `AboutPageSettings`, `MenuSettings`, `ActivityLogPage` are all `Pages` with a paired Blade view under `resources/views/filament/pages/`. Use this pattern for any new admin screen that isn't a straightforward CRUD resource.
- `HomepageSettings`, `AboutPageSettings`, and `MenuSettings` manually decode/encode JSON and normalize Filament's `FileUpload` return shape (e.g. `array_values(array_filter($value))[0] ?? null`) in `mount()`/`save()`. This is brittle by nature (tied to Filament's current return shape) — if a Filament upgrade changes `FileUpload`'s value shape, check these files first.
- Repeater items that need manual ordering (e.g. `AboutPageSettings`'s `certificates`/`gallery`/`timeline`) get an explicit numeric `sort_order` field alongside Filament's built-in `->reorderable()` drag handle, and the consuming controller sorts by it (`BlogController::sortBySortOrder()`) before passing data to the view. Reuse this pattern — plain numeric field + a small `usort` in the controller — rather than relying on array order alone, since admins may want to reorder without dragging.
- Fallback/default content for a CMS-managed page lives in the **Blade view**, not the Filament Page class — `mount()` loads raw `SiteSetting` values (null if unset) exactly like `HomepageSettings`, and the public template supplies the current design's copy as the second argument to a `$v($key, $default)` helper closure (see `home.blade.php`/`about.blade.php`). This means a freshly-added content block renders identically to today until an admin actually edits it in `/admin` — do not pre-fill defaults in the Filament form itself.
- Every admin-facing label must stay in **plain English understandable by a non-developer business owner** (Ehsan). Do not expose internal field names, JSON structures, or developer jargon in any Filament label/helper text — follow the existing tone in `ArticleForm` (e.g. "Publish date" with helper text "For 'Scheduled': set a future date/time — the article goes live automatically at that moment.").
- `ArticleForm`'s slug auto-fill (`afterStateUpdated` on `title` → `Str::slug` into `slug`) is the standard pattern for any future slug-bearing resource — reuse it rather than inventing a new slugging approach.
- Featured images use Filament's `FileUpload` directly to the `public` disk, `articles` directory, with **no resizing/compression step** — see Image Optimization Rules below before adding new image fields.

## 7. Multilingual Architecture

- Two locales today: `en` (default) and `tr`. There is no i18n package (no Laravel localization files, no `lang/` directory in active use) — translation is handled entirely by **duplicating routes, controllers methods, views, and database rows** per locale, not by string-translation files. Do not introduce Laravel's `__()` translation-file system for page content; it does not fit this project's model (content is data-driven from the database, not static UI strings).
- Route convention: English routes are bare (`/`, `/blog`, `/blog/{slug}`), Turkish routes are prefixed with `/tr` (`/tr`, `/tr/blog`, `/tr/blog/{slug}`). Follow this exact prefix convention for any new localized route — do not use a `{locale}` route parameter or subdomain-based localization; it would require a larger refactor and hasn't been decided.
- Article rows carry their own `locale` column and are queried with `Article::locale('en')` / `Article::locale('tr')`. The `translation_of` foreign key links a translated pair together (see Laravel Conventions above) — always set this when creating a translated counterpart of an existing article, so `BlogController::renderShow()` can surface the "other language" link.
- `hreflang` tags are present in `master.blade.php` but **commented out** with the note "فعال‌سازی بعد از آماده شدن نسخهٔ ترکی" ("enable after the Turkish version is ready"). Do not silently re-enable these — see Important Project Decisions.
- When adding a new public page, always create both the `en` and `tr` versions together in the same change, plus both route registrations. Never ship an English-only page as "TR to follow later" — that is exactly the pattern that has caused drift in the past (git history shows CSS/layout fixes landing for one locale before the other).

## 8. SEO Rules

- Every public page must set, via `@section`/`@yield` in the master layout: `title`, `meta_description`, `canonical`, and Open Graph (`og_title`, `og_description`, `og_type`). Never leave a new page on the master layout's generic defaults — write page-specific copy.
- `robots` meta is `index,follow` globally (`master.blade.php`) — there is no per-page noindex mechanism today. If a page should ever be excluded from indexing (e.g. a preview page), do not rely on this meta tag; the `/preview/article/{article}` route already protects itself via Laravel's `signed` URL middleware instead, which is the correct approach for non-public pages — prefer signed/authenticated access over `noindex` for anything that must not be publicly discoverable.
- `sitemap.xml` (`SeoController::sitemap`) and RSS feeds (`/feed`, `/tr/feed`) are generated **dynamically from the database on every request** — they are always in sync with published articles by construction. Do not switch these to a cached/static file unless a real performance problem is measured; freshness here has been an explicit design goal (see the Farsi comment in `SeoController`: "نقشه‌ی سایت داینامیک — همیشه به‌روز").
- `SeoController::sitemap()` currently includes only `/`, `/about`, `/blog`, `/tr`, `/tr/about`, `/tr/blog` as static URLs plus all published articles. When adding a new static public page, add its URL (both locales) to this list.
- JSON-LD structured data has a dedicated `@yield('json-ld')` slot in the master layout, populated today on `about.blade.php`/`tr/about.blade.php` (`Person` schema) and `blog-post.blade.php`/`tr/blog-post.blade.php` (`Article` schema) — `home`/`blog` index pages still use the layout's empty default. When adding structured data to a new page, inject it through this slot — do not hand-write `<script type="application/ld+json">` blocks elsewhere in the templates.
- `og:image` support exists via `@yield('og_image')` in both master layouts (rendered only if non-empty, via `$__env->yieldContent('og_image')` — see `master.blade.php`). The About page sets it from `about.{locale}.seo_og_image` (a Filament `FileUpload`, falls back to nothing if unset). Wire up the same `@section('og_image', ...)` pattern for any other page that gets a manageable OG image (e.g. blog posts, using the article's `image_path`) rather than inventing a second mechanism.

## 9. AI Search Optimization

The site's content (self-defense/BJJ instruction, articles) is exactly the kind of material AI answer engines (ChatGPT browsing, Perplexity, Google AI Overviews, Claude) surface when it is structured for extraction. Rules for this project specifically:
- Keep article content in clean, semantic HTML from the Filament `RichEditor` (`ArticleForm::body`) — real `<h2>`/`<h3>` headings, real `<ul>`/`<ol>` lists, no content locked behind JS-only rendering. Since this site is already server-rendered Blade with no client-side rendering gate, this is preserved by default — do not introduce a JS-rendered content path that would hide article text from non-JS crawlers.
- The JSON-LD `@yield('json-ld')` slot is already populated with `Article` schema on `blog-post.blade.php`/`tr/blog-post.blade.php` and `Person` schema on `about.blade.php`/`tr/about.blade.php`. `LocalBusiness` structured data on the home page is still missing — that remains one of the highest-leverage additions for AI-answer-engine visibility (see Future Development Guidelines).
- Write article excerpts (`Article::excerpt`) as genuinely standalone, quotable summary sentences — they are used verbatim in the RSS `<description>` and (once added) in meta descriptions; AI engines lift these directly.
- Do not gate any article content behind interaction (accordions that hide body text from the DOM, "read more" that lazy-loads via JS after scroll) — keep full article body present in the initial HTML response, since that's what both search and AI crawlers read.
- A `robots.txt` exists in `public/` — verify it does not block `/blog/*` or asset paths needed to render article images before assuming AI/search crawlers can fully read the site.

## 10. Analytics & Tracking

**Current state: no analytics or tracking integration exists anywhere in this codebase.** This was verified by searching the entire `resources/` tree and all Blade views for Google Analytics (`gtag`, `googletagmanager`), Microsoft Clarity, Meta/Facebook Pixel (`fbq`), Hotjar, and any generic "analytics" reference — there are zero matches outside of vendored Filament admin-panel JS (chart widgets, Livewire's `echo.js`), which are unrelated library internals, not tracking scripts. Concretely:
- **Google Analytics 4**: not implemented.
- **Microsoft Clarity**: not implemented.
- **Meta Pixel**: not implemented.
- No other analytics/tracking service (Hotjar, Plausible, Fathom, etc.) is present either.

Rules going forward:
- If asked to add an analytics integration, document it here (which service, where the snippet lives — prefer the `@yield`-based head section in `master.blade.php`/`master-tr.blade.php` so it's consistent across locales — and whether it loads on every page or is conditional) in the same change that adds it.
- **Never remove or modify existing tracking without explicit approval** — once an integration is added, treat its snippet/config as protected in the same way as the two "must never be changed" categories elsewhere in this file; don't touch it as a drive-by cleanup.
- Since this is a Turkey/Istanbul-facing business site, any tracking added later should be added with consent/privacy regulations (KVKK, and GDPR for EU visitors) in mind — this is a placeholder note for whoever implements it, not a currently-solved concern.

## 11. Core Web Vitals Rules

- The public site has **no client-side JS framework** and minimal `app.js` — this is a genuine LCP/INP advantage; do not regress it by adding a heavy JS dependency for a purely visual effect.
- Fonts: `master.blade.php` loads Google Fonts "Poppins" via a render-blocking `<link>` (with `preconnect` hints already in place) — this is a current LCP/CLS risk (external font request blocks text paint, and swap can cause layout shift). Do not make this worse by adding more external font weights than the four already loaded (400/500/600/700/800 currently — trim if a redesign doesn't need all of them); consider self-hosting the font file as a future improvement (see Future Development Guidelines) rather than adding another Google Fonts request.
- Vite is configured to prefetch a **different** font ("Instrument Sans" via Bunny Fonts) that is never actually used in `master.blade.php` — this is dead configuration wasting a build step, not a live CWV cost, but should not be extended (don't add real usage of a second font family without removing the unused one first).
- Images (hero, about-section, footer background, article featured images) are referenced directly via `<img>`/CSS `background-image` with real files under `public/` — there is currently no responsive `srcset`, no explicit `width`/`height` attributes enforced as a rule, and no lazy-loading attribute convention. Any new image markup should set explicit `width`/`height` (or `aspect-ratio` in CSS) to prevent CLS, and use `loading="lazy"` for below-the-fold images — the hero/first-viewport images must NOT be lazy-loaded (that would hurt LCP).
- The hand-rolled CSS in `master.blade.php` is inlined in a single `<style>` block per page load rather than a separate cached CSS file — acceptable at current site size, but if the CSS block grows much larger, moving it to a Vite-built, cacheable stylesheet becomes worth revisiting (flag it, don't do it preemptively).

## 12. Accessibility (a11y) Rules

- **Preserve keyboard navigation.** All interactive elements (nav links, carousel controls, form inputs, footer links) must remain reachable and operable via keyboard; do not add click-only interactions (e.g. hover-only dropdowns, div-based buttons with no keyboard handler) when redesigning a section.
- **Keep focus-visible styles.** `master.blade.php` already defines `a:focus-visible,button:focus-visible{outline:2px solid var(--gold);outline-offset:2px}` — preserve this (or an equivalent visible focus indicator) on any new interactive element; never set `outline:none` without providing a replacement focus style.
- **Respect `prefers-reduced-motion`.** `master.blade.php` already includes an `@media (prefers-reduced-motion: reduce)` block that disables `scroll-behavior: smooth` and turns off animations/transitions — any new animation, carousel transition, or scroll effect must be added inside (or otherwise respect) this same media query, not bypass it.
- **Maintain semantic HTML and ARIA where appropriate.** Continue using real headings (`h1`/`h2`/`h3`), real lists, and real `<button>`/`<a>` elements rather than generic `<div>`s with click handlers; add ARIA attributes (`aria-label`, `aria-expanded`, etc.) only where the semantic HTML element itself can't convey the state (e.g. a mobile menu toggle).
- **Never reduce accessibility while making visual changes.** Pixel-matching `ehsandibazar.com` (see Brand Identity) must not come at the cost of keyboard access, focus visibility, reduced-motion support, or semantic structure — if the reference site itself lacks these, this project should still keep them rather than copying that regression.

## 13. Coding Standards

- PHP: PSR-12 via Pint's default Laravel preset. Run `./vendor/bin/pint` before every commit that touches `.php` files.
- No new abstractions (services, repositories, DTOs, form requests) unless a concrete second use case exists — this codebase's controllers are deliberately thin and direct; match that altitude.
- Farsi comments explaining *why* (not *what*) are the established convention in domain logic files — see Laravel Conventions above. New comments in these files should follow the same language and same "explain the non-obvious constraint" purpose, not restate the code.
- Blade views: no component library, no `<x-component>` abstractions currently in use for the public site — sections are built as plain HTML/Blade with `@yield`/`@section`. Match this flat style rather than introducing Blade components mid-redesign unless asked to.
- Every new public route/controller method must have an explicit EN and TR counterpart added in the same change (see Multilingual Architecture) — a PR/commit that adds only one locale is incomplete by this project's standard.
- Do not add dependencies (Composer or npm) for something a few lines of first-party code can do — this project has stayed intentionally dependency-light (5 runtime Composer packages, no JS framework). Any new dependency should be justified against that baseline.

## 14. Development Principles

- **Never make assumptions.** If the current behavior, data shape, or intent of a piece of code is unclear, read the code (and this file) until it is clear, or ask — do not guess and proceed.
- **Always inspect the existing implementation before changing code.** Read the relevant controller/model/view/Filament class in full before editing it; this codebase has non-obvious intentional behavior (e.g. the `scopePublished()` scheduler safety net, the schemaless `SiteSetting` store) that is easy to break by pattern-matching from a different codebase's conventions.
- **Prefer incremental improvements over large refactors.** Fix the specific thing asked; do not use a task as cover to reshape adjacent code, restructure folders, or "clean up while I'm in here" unless that cleanup was requested.
- **Never rewrite working code without measurable benefit.** "This could be written more elegantly" is not sufficient justification to touch code that is functioning correctly — rewrite only when there's a concrete bug, performance measurement, or explicit request behind it.
- **Explain architectural trade-offs before implementing them.** When a change has more than one reasonable approach (e.g. where to cache, whether to add a dependency, how to structure a new bilingual feature), lay out the options and trade-offs for the user before writing code, rather than silently picking one.
- **Preserve backward compatibility whenever possible.** Don't change route URLs, database column meanings, Filament field keys/`SiteSetting` key names, or public method signatures in ways that would silently break existing content, bookmarked URLs, or in-progress admin edits, unless the change is explicitly about that.

## 15. Security Rules

**Known unresolved issue — treat as top priority if you have any bandwidth to spend on non-feature work:**

`routes/web.php` currently defines two **completely unauthenticated** routes:
```php
Route::get('/system-cache-flush-7k2p9x', function () { Artisan::call('cache:clear'); ... });
Route::get('/system-migrate-9x4kq2', function () { Artisan::call('migrate', ['--force' => true]); ... });
```
These were added as temporary deploy-ops helpers (git history: "Add temporary migrate route for activity_log table") and never removed. Anyone who discovers either URL can force a database migration or wipe all caches on production with no credentials. **Do not add more routes like this.** If you touch `routes/web.php` for any reason, flag these to the user; the correct fix is to run `migrate`/`cache:clear` via SSH/deploy pipeline, or at minimum gate these behind `auth` middleware plus a signed URL, never a bare public GET.

Other standing rules:
- Never commit a real `.env` file, `database/database.sqlite` with real data, or any credential. `.env.example` is the only env file that belongs in git.
- `APP_DEBUG` must be `false` on any environment reachable by the public — verify this on the actual server config (outside this repo) before every deploy; `.env.example` currently defaults it to `true`, which is correct for local dev only.
- The `/preview/article/{article}` route is `signed` — this is the correct pattern for "must not be publicly guessable" content. Reuse `->middleware('signed')` for any future preview/draft-access route rather than inventing an obscure-slug pattern (the two routes above are the cautionary example of why obscure slugs are not security).
- `BCRYPT_ROUNDS=12` is the configured hashing cost for the admin `User` password — do not lower it.
- Filament `/admin` is the only authenticated surface in the app; there is no public user registration, no public account system, and none should be added without a specific product reason — this is a single-admin CMS, not a multi-tenant app.

## 16. Git Workflow **(Convention to adopt)**

There is no formally documented branching convention in this repo yet (history so far is a linear sequence of commits on `main`, plus review/session branches like `claude/project-review-nwy3kt`). Adopt the following going forward:
- `main` is always deployable — do not leave it in a broken state.
- Create a feature/fix branch per unit of work (e.g. `fix/unauthenticated-maintenance-routes`, `feat/homepage-hero-video`); avoid committing directly to `main` for anything beyond a trivial one-line fix.
- Commit messages should describe the observable change and, where relevant, why (matching the existing history's style — see recent commits like "About image: bottom-left anchor on desktop, flush to section bottom on mobile (no gap below)": specific, states before/after behavior).
- Run `./vendor/bin/pint` and `php artisan test` before opening a PR/merging (see Testing Strategy — this will be nearly meaningless until real tests exist, but keep the habit ready for when they do).

## 17. Staging Workflow **(Convention to adopt)**

No staging environment currently exists in any config found in this repo (no `.env.staging`, no staging-specific deploy config, no second Filament panel/environment guard). Recommended baseline once a staging environment is provisioned:
- A staging deploy should use its own `.env` with a separate SQLite file (or separate DB) and its own `APP_URL`, so sitemap/canonical/OG URLs generated via `url()`/`url()->current()` are correct for that environment automatically (the codebase already builds all URLs from `APP_URL`/request context, not hardcoded domains — preserve that; never hardcode `trainwithehsan.com` into PHP or Blade).
- `APP_DEBUG=true` is acceptable on staging only, never on the environment the public/search engines can reach.
- Verify the `articles:publish-due` scheduler cron is wired on staging too if content scheduling needs to be tested end-to-end there.

## 18. Deployment Workflow **(Convention to adopt)**

No CI/CD pipeline or deploy script exists in this repo today (no `.github/workflows`, no Forge/Envoyer/Vapor config, no `Procfile`/`Dockerfile`). `composer.json`'s `scripts.setup` (`composer install` → copy `.env` → `key:generate` → `migrate --force` → `npm install` → `npm run build`) is the closest thing to a documented setup procedure and should be treated as the canonical "first deploy" sequence. For every deploy after the first:
1. Pull the new code.
2. **Take a fresh backup of `database/database.sqlite` first** — see SQLite Backup Strategy below; never run a migration on prod without a same-day backup in hand.
3. `composer install --no-dev --optimize-autoloader` (production).
4. `npm run build` (rebuild Vite assets — Blade references built assets via the Vite directive, so stale assets will 404/serve old CSS/JS if this is skipped).
5. `php artisan migrate --force` **run manually via the deploy process/SSH — never via a public route** (see Security Rules; this replaces the two routes that must eventually be removed).
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache` for production performance.
7. Confirm the scheduler cron (`* * * * * php artisan schedule:run`) is present on the server so `articles:publish-due` actually fires every 5 minutes — this is invisible if missing (articles just silently never auto-publish).
Introducing a real CI/CD pipeline (even a minimal GitHub Actions workflow running Pint + `php artisan test` on push) is a recommended near-term improvement — see Future Development Guidelines.

## 19. SQLite Backup Strategy

The production database is a **single SQLite file** (`database/database.sqlite`), not a server-based DB — this changes what "backup" means here and makes it easy to overlook:
- **Where backups should come from**: file-level copies of `database/database.sqlite`, taken at the filesystem/hosting level (a cron job that copies the file to offsite/object storage — e.g. S3, a separate backup host, or the hosting provider's snapshot feature), not a `mysqldump`-style logical export. There is no backup script, scheduled command, or offsite-copy mechanism in this repository today — this must be configured at the server/hosting layer, outside this codebase, and is not yet done as far as this repo's contents show.
- **Regular backups are required, not optional, before deployments or migrations.** SQLite has no built-in point-in-time recovery or replication — if a migration corrupts data or a deploy goes wrong, the *only* way back is a prior copy of the file. Treat "take a backup" as a mandatory step before any `php artisan migrate` on production (see Deployment Workflow step 2), and additionally on a regular schedule (e.g. daily) independent of deploys, so an incident between deploys is still recoverable.
- **This is an operational requirement, not a nice-to-have.** Whoever owns the production server must have a working, tested restore path for this file before this project should be considered production-safe. If you're asked to help with deployment/infrastructure work, confirm this exists before assuming it does.

## 20. Article Publishing Workflow

This is real, implemented behavior — document it precisely:
- An article's `status` is one of `draft`, `scheduled`, `published` (`ArticleForm`).
- `Article::scopePublished()` treats an article as publicly visible if `status = published`, **or** if `status = scheduled` AND `published_at <= now()` — this is a deliberate safety net (Farsi comment: "even if cron stops working, the article still shows up on time") so a stalled scheduler never blocks content from appearing once its time has passed.
- Separately, `php artisan articles:publish-due` (run every 5 minutes via `routes/console.php`) proactively flips due `scheduled` articles to `published` and clears `cache`/`view` caches — this is what makes the homepage/blog list reflect the newly-published article immediately rather than waiting for a cache TTL.
- `EditorialCalendar` (Filament page) lets Ehsan drag an article to a new date on a calendar to reschedule it — this writes to the same `published_at`/`status` fields, nothing calendar-specific in the schema.
- When editing this pipeline, preserve both halves (the scope's time-based fallback, and the command's active flip + cache clear) — removing either changes observable behavior (removing the scope fallback means a stalled scheduler hides due content indefinitely; removing the command's cache clear means published content is delayed until the next natural cache expiry).
- Every article change is recorded via `spatie/laravel-activitylog` (title/locale/status/published_at/category, dirty-only) and visible in the Filament `ActivityLogPage` — don't bypass Eloquent (e.g. raw DB updates) for article status changes, or this audit trail silently breaks.

## 21. Image Optimization Rules

No image optimization pipeline exists today — this is a gap, not a hidden feature. Currently:
- Featured images (`Article::image_path`) and inline body images are uploaded as-is via Filament `FileUpload` to the `public` disk with no resizing, no format conversion, no compression step (no `intervention/image`, no `spatie/laravel-medialibrary` conversions, nothing in `composer.json` performs this).
- Static design images (hero, about section, footer background, logos) are committed directly under `public/` and referenced by fixed path.

Rules for any new image-handling work:
- Do not assume images are pre-optimized — if adding responsive images or a CDN, treat every existing image path as raw/unoptimized input.
- If introducing an optimization step, prefer a build-time or upload-time transform (e.g. an Intervention Image step in the `FileUpload` save pipeline, or a queued job) over a request-time transform, to avoid adding latency to public page loads.
- Any new image field in Filament should follow `ArticleForm`'s existing `FileUpload::make(...)->image()->disk('public')->directory(...)` pattern for consistency, even before optimization is added — don't invent a second upload mechanism.
- Prefer serving modern formats (WebP/AVIF) with a JPEG/PNG fallback once an optimization step exists; until then, keep uploaded images to sane pre-optimized sizes manually (this is a manual/process discipline today, not enforced by code).

## 22. Performance Rules

- Database: SQLite with session/cache/queue all on the same database connection (`database` driver for all three). This is fine at current traffic; if concurrent write load grows (many simultaneous sessions + queued jobs + cache writes), moving cache/session to Redis and/or the DB to MySQL/Postgres is the first lever to pull — do not attempt in-place SQLite tuning workarounds instead.
- No caching layer sits in front of `SiteSetting` reads or the homepage/blog article queries today — each page load queries fresh. At current content volume this is not a measured problem; if you add caching here, invalidate it from the same places that already clear cache today (`PublishDueArticles`, the Filament save hooks) rather than introducing a second, parallel cache-invalidation path.
- The two maintenance routes (`system-cache-flush-*`, `system-migrate-*`) are also, incidentally, a performance/availability risk since anyone can trigger a full cache clear at will — another reason they must be fixed (see Security Rules).
- Keep `composer install --no-dev --optimize-autoloader` and `artisan config:cache`/`route:cache`/`view:cache` as standard for any production deploy (see Deployment Workflow) — these are the main have-you-done-this-yet performance checks for a Laravel app of this shape.

## 23. Testing Strategy

**Current state: there is effectively no test coverage.** `tests/Feature/ExampleTest.php` asserts `/` returns HTTP 200, and `tests/Unit/ExampleTest.php` asserts `true === true` — both are the unmodified Laravel scaffold, not written for this project. None of the following are tested at all today: `Article::scopePublished()` time-based logic, `BlogController` (any of the 6 public methods, either locale), `SeoController` sitemap/RSS XML correctness, `PreviewController`'s signed-URL gate, `PublishDueArticles`, or any Filament resource/page.

Priority order for adding real tests (highest-value first):
1. `Article::scopePublished()` — feed it draft/scheduled-future/scheduled-due/published rows and assert visibility; this is the single most important piece of business logic in the app and the easiest to silently break.
2. `PublishDueArticles` — assert it flips due scheduled articles to published and leaves others untouched.
3. `BlogController` feature tests for both locales — home/index/show return 200, show correct articles, 404 on missing slug.
4. `SeoController` — sitemap includes only published articles, RSS feed is well-formed XML and locale-filtered.
5. `PreviewController` — unsigned access is rejected, signed access works even for a draft article.

Run tests with `php artisan test` (or `composer test`, which clears config cache first). Use `laravel/pint` for style, not a separate linter. Do not adopt Pest unless the user asks — the project is on plain PHPUnit today (`phpunit/phpunit` ^12.5) with no Pest dependency installed.

## 24. Important Project Decisions

These are decisions already made — do not silently reverse them:
- **Pixel-parity with `ehsandibazar.com` is a deliberate, ongoing goal**, not an accident of a rushed build — a large fraction of commit history is dedicated to matching exact CSS values, image positioning, and layout from that reference site (see Brand Identity). When touching public-site visuals, check whether the current behavior was arrived at through this matching process before "fixing" it — it may be intentionally unusual to match the reference.
- **hreflang tags are intentionally disabled** (commented out in `master.blade.php`) pending the Turkish site being considered ready/complete. Do not re-enable them as a drive-by fix — that decision belongs to whoever is tracking TR-site completeness.
- **Bilingual content is two separate database rows**, not column-per-locale — see Multilingual Architecture. This has been the model since the `articles` table migration and should not be changed without a full data migration plan.
- **`SiteSetting` is a deliberately schemaless key/value CMS**, not a design placeholder awaiting "real" tables — this lets Ehsan edit homepage content from Filament without requiring a migration for every new content block.
- **No JS framework on the public site** — this is a stated architectural simplicity/performance choice (see Core Web Vitals), not a missing feature.
- **No analytics/tracking is installed today** — this is a verified current fact, not an oversight to silently "fix"; see Analytics & Tracking before adding any.
- **The two temporary maintenance routes are a known, unresolved defect** — they exist because of a real deploy pain point (running migrations on a host without SSH-based deploy access, per the git history's activity_log migration saga), not because anyone considers them acceptable long-term. Don't remove them without offering a replacement deploy mechanism (see Deployment Workflow) — but do flag/fix them as the top priority the next time you have a maintenance window.

## 25. Things That Must Never Be Changed

- Do not change the `articles` table's two-row-per-translation model (`locale` + `translation_of`) without an explicit request and a data migration plan — every controller, scope, and Filament form assumes this shape.
- Do not remove the `scopePublished()` time-based fallback for `scheduled` articles — it is a deliberate resilience mechanism against scheduler downtime, not redundant logic.
- Do not hardcode `trainwithehsan.com` (or any other absolute domain) into PHP/Blade in place of `url()`/`url()->current()`/`APP_URL` — every URL-building call in the app already derives from request/config context; hardcoding breaks staging and local dev silently.
- Do not delete or bypass the Spatie activity log hooks on `Article` — Ehsan relies on the `ActivityLogPage` to see what changed and when.
- Do not add authentication/registration for public site visitors — this is a single-admin CMS by design; `/admin` is the only login surface.
- Do not re-enable hreflang tags as an incidental change (see Important Project Decisions).
- Do not introduce a JS framework, a component library, or a CSS framework migration (e.g. swapping the hand-rolled CSS for a full Tailwind rewrite) as a side effect of an unrelated task — these are significant architectural changes that need to be explicitly requested (see Brand Identity: never redesign UI without explicit approval).
- Do not make the two maintenance routes (`/system-cache-flush-*`, `/system-migrate-*`) "more convenient" (e.g. by adding more Artisan commands to them) — they should be removed/secured, never expanded.
- Do not remove or modify any analytics/tracking snippet without explicit approval, once one exists (see Analytics & Tracking).
- Do not reduce keyboard access, focus visibility, reduced-motion support, or semantic HTML/ARIA while making a visual change (see Accessibility Rules).

## 26. Future Development Guidelines

Ordered roughly by impact:
1. **Fix the two unauthenticated maintenance routes** — highest priority, real production security exposure (see Security Rules).
2. **Confirm/establish a real SQLite backup mechanism on the production server** — see SQLite Backup Strategy; this is currently unverified/likely missing and is a data-loss risk.
3. **Stand up minimal CI** — a GitHub Actions workflow running `pint --test` and `php artisan test` on every push/PR costs little and catches regressions the current zero-test setup cannot.
4. **Write the priority-1-through-5 tests listed in Testing Strategy**, starting with `Article::scopePublished()`.
5. **Add `LocalBusiness` JSON-LD structured data** to the home page — `Article` (blog posts) and `Person` (about page) schema are already implemented; the home page is the remaining gap.
6. **Wire up `og:image` for blog posts** using the article's featured image — the `@yield('og_image')` mechanism already exists (used by the About page's `seo_og_image` field) and just needs the same `@section('og_image', ...)` call added to `blog-post.blade.php`/`tr/blog-post.blade.php`.
7. **Consider a real image optimization step** (Intervention Image or similar) in the Filament `FileUpload` pipeline before image volume grows.
8. **Revisit the EN/TR duplication** once the Turkish site is considered complete and stable — evaluate whether a shared-view + locale-parameter approach is worth the refactor cost at that point (not before; premature to do while TR content/design is still catching up to EN, per the hreflang decision above). This is exactly the kind of large refactor the Development Principles above say to avoid without measurable benefit and explicit sign-off.
9. **Update `README.md`** to describe this project specifically (currently the unmodified Laravel skeleton README) and remove the unused `resources/views/welcome.blade.php`.
10. **Resolve the font inconsistency** (Vite prefetches "Instrument Sans" via Bunny Fonts; the site actually renders "Poppins" from Google Fonts) — either wire up the configured font or remove the unused prefetch, and consider self-hosting the chosen font for Core Web Vitals.
11. Set `APP_NAME` in `.env.example` (and real env) to something other than the default `Laravel`.
12. **Decide on and implement an analytics stack** (GA4, Clarity, Meta Pixel, or otherwise) once the business is ready to track visitor behavior — currently deliberately undocumented because nothing is installed (see Analytics & Tracking).

When in doubt about whether a change fits this project's grain, re-read sections 2, 14, 24, and 25 above before proceeding.

## 27. Keeping CLAUDE.md Updated

This file is the **single source of truth** for future Claude Code sessions working on this repository — it exists so a new session can be productive immediately without re-exploring the entire codebase from scratch.

- Whenever a change affects architecture, conventions, deployment, SEO, the CMS/Filament setup, workflows (git/staging/deployment/publishing), or any of the "Important Project Decisions"/"Things That Must Never Be Changed" — **update the relevant section of this file in the same commit as the code change**, whenever appropriate to do so.
- If this file and the actual codebase ever disagree, that is a bug in this file — fix the documentation, don't let it quietly drift out of date.
- Prefer editing the existing section that already covers a topic over bolting on a new one; keep the numbering contiguous when sections are added or removed, and update any cross-references (e.g. "see Section N") that shift as a result.
