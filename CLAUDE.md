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
- **API layer — exactly one, keep it that way**: the only API is the AI Import API (`routes/api.php`: `POST /api/ai-import` and `POST /api/ai-import/validate`), authenticated by first-party hashed bearer tokens (`api_tokens` table, managed in AI Studio → API Tokens; no Sanctum — deliberately dependency-light), rate-limited per token (`throttle:ai-import-api`). **API policy: it can never publish** — every import is forced to `draft` regardless of the payload's `publish_status`, and publishing happens only through the panel (Draft Queue → admin approval → existing workflow). `?queue=1` (or `"queue": true`) runs the import through the database queue via the `ImportAiArticle` job. Everything else on the site remains full-page HTML (Blade) or XML (sitemap/RSS) — do not add further **inbound** API surface without a real client needing it. (This doesn't cover **outbound** calls — see the next bullet.)
- **Outbound third-party AI APIs — five vendor implementations behind one abstraction, orchestrated by `App\Services\AiAssistant\ProviderManager`** (see Section 24 for the full design). `App\Services\AiAssistant\Contracts\AiProvider` (`respond(...): string`) remains the low-level per-vendor contract — unchanged since it was first introduced — implemented today by `AnthropicProvider`, `OpenAiProvider`, `GeminiProvider`, `GrokProvider`, `DeepSeekProvider` (the last three share an `OpenAiCompatibleProvider` base class, since OpenAI/xAI/DeepSeek all speak the same Chat Completions shape), plus `NullProvider` as the no-key-configured fallback. `ContentAssistantService` no longer calls an `AiProvider` directly — every call goes through `ProviderManager::respond(..., actionKey: $field)`, which resolves which provider/model to use per call (per-field override → global default → legacy `.env` fallback, with optional failover to a second provider) and logs the result to `ai_usage_logs`. Credentials/models/routing are configured at `/admin` (AI Providers, AI Routing — see Section 24), encrypted at rest; `config('services.anthropic.*')` / `ANTHROPIC_API_KEY` in `.env` is preserved untouched as the automatic fallback for installs that configure nothing in the database, so existing behavior for anyone who only ever set `ANTHROPIC_API_KEY` is unchanged. Adding a 6th vendor is one new `AiProvider` class + one entry in `ProviderManager::DRIVERS` + one seeded `ai_provider_configs` row — no other code changes.

## 4. Folder Structure

```
app/
  Console/Commands/PublishDueArticles.php   Scheduled auto-publish job — also advances any linked `ContentPlan` to `published` and fires `PublishingCompleted`, see Section 25
  Console/Commands/BackfillMediaLibrary.php  One-off `media:backfill` — registers pre-DAM article/page images in the Media Library
  Console/Commands/NotifyApproachingDeadlines.php   `content-plans:notify-deadlines`, hourly — warns about `ContentPlan.due_at` within 24h, see Section 25
  Console/Commands/AgentAudit.php           `agent:audit`, weekly — runs synchronously (like `articles:publish-due`) so it doesn't depend on a queue worker, see Section 29
  Filament/
    Resources/Articles/                     ArticleResource + Schemas/ArticleForm.php, Tables/ArticlesTable.php, Pages/
    Resources/Pages/                        PageResource (standalone site pages: privacy, terms, FAQ, ...) — same layout as Articles
    Resources/NewsletterSubscribers/        NewsletterSubscriberResource — search/filters, CSV export, bulk resend verification, bulk send (queued)
    Resources/ImportLogs/                   Import History (read-only, "AI Studio" nav group): search/filters, validation-report modal, rollback action, stats widget
    Resources/AiTemplates/                  Reusable JSON/Markdown skeletons loadable on the AI Import page
    Resources/AiPrompts/                    Prompt Library — saved AI instructions with one-click copy
    Resources/AiProfiles/                   AI Profiles — provider name + defaults that fill gaps in imported content
    Resources/ApiTokens/                    API tokens for the AI Import API — create (shown once) / revoke, last-used tracking
    Resources/Tags/                          TagResource (nav "Tags", "Content Planner" group) — plain List/Create/Edit/Delete, see Section 25
    Resources/WorkflowStages/                WorkflowStageResource (nav "Workflow Stages") — reorderable, default-stage/checklist-template management, see Section 25
    Resources/ContentPlans/                  ContentPlanResource — Create/Edit form + shared table class only (`shouldRegisterNavigation() => false`, ContentPlanner is the real entry point), see Section 25
    Pages/AiImport.php                       "One Click Publish" — paste-area importer for AI-generated articles (JSON/Markdown/HTML/XML/custom [[FIELD]] markers → validate → preview (incl. tags/keywords/SEO+OG/internal+external links) → manual corrections → import), with template/profile pickers and a Roll back action on eligible rows in Recent Imports — see Section 28
    Pages/DraftQueue.php                     Imported drafts awaiting review — edit / signed preview / publish-now
    Pages/EditorialCalendar.php              Superseded — now a thin redirect to the Content Planner's Calendar view (`shouldRegisterNavigation() => false`), see Section 25
    Pages/ContentPlanner.php                 Content Planner (nav "Planner", "Content Planner" nav group) — one page, four switchable views (Kanban/Calendar/Table/Dashboard), see Section 25
    Pages/HomepageSettings.php               Homepage content-block editor (per locale)
    Pages/AboutPageSettings.php              About page content-block editor (hero, stats, certificates, gallery, timeline, CTA, SEO — per locale)
    Pages/MenuSettings.php                   Header nav menu editor (per locale)
    Pages/FooterSettings.php                 Footer editor (newsletter bar, bg/logo, link columns, socials, contact, copyright — per locale)
    Pages/ActivityLogPage.php                Read-only activity log viewer
    Pages/MediaLibrary.php                   Digital Asset Management (DAM): folders, drag-and-drop multi-upload, search/filters, preview, ALT/usage tracking, replace, delete-guard
    Pages/SeoCenter.php                      Read-only SEO audit dashboard: 10 issue categories, filters, CSV export, quick edit links — see Section 8
    Pages/InternalLinkingCenter.php          Internal-linking dashboard, rule-based link suggestions, link graph — see Section 22
    Pages/AiContentAssistant.php             Thin ~15-line shell mounting App\Livewire\AiAssistantPanel for one Article/Page — kept as a standalone full-page fallback/deep link; the primary UX is the embedded sidebar (EditArticle/EditPage) — see Section 23
    Pages/AiActionRouting.php                Global default/failover provider + per-ActionRegistry-field provider override, grouped under collapsible sections (SEO/Content/Translation/Media/Links/Schema) — see Section 24
    Pages/BrandMemory.php                    Central brand knowledge every AI Studio prompt reads automatically — grouped sections, EN/TR/FA value tabs, version history, Preview Prompt — see Section 26
    Pages/AiAgentDashboard.php               "AI Agent" — sixteen-category proactive audit dashboard (category sidebar/counts, filters, per-finding Review/Preview Fix/Approve/Reject), reuses SeoAuditService/LinkGraphService/ContentReviewService for detection and RunAiContentGeneration/TranslateArticleDraft/GenerationApplier for one-click fixes — see Section 29
    Resources/AiProviderConfigs/             AiProviderConfigResource — List/Edit only (5 rows seeded, slug-bound to ProviderManager::DRIVERS, so no Create/Delete): encrypted API key (blank = keep unchanged), base URL/model/tokens/temperature/timeout, admin-maintained model-catalog repeater, Test Connection + Set as Default row actions — see Section 24
    Resources/AiUsageLogs/                   AiUsageLogResource — fully read-only (canCreate/canEdit/canDelete all false), filters (provider/status/action/user/date), per-row error detail modal, Export CSV bulk action — see Section 24
    Resources/KnowledgeEntries/               KnowledgeEntryResource (nav "Knowledge Entries", its own "Knowledge Base" nav group) — full CRUD: title/category (free text with a suggested-category datalist)/locale/content/source/status/priority/pinning/expiry/tags + document attachments, see Section 27
    Pages/SystemMaintenance.php              Admin-auth-gated "run pending migrations" / "clear cache" buttons — replaces the old public unauthenticated maintenance routes, see Security Rules
    Widgets/ArticleStatsOverview.php         Dashboard stats widget
  Http/Controllers/
    BlogController.php                       Home, about, blog index, blog show — EN and TR variants
    PageController.php                       Standalone pages (privacy, terms, FAQ, ...) — EN and TR show routes
    NewsletterController.php                 Newsletter subscribe (AJAX) / verify / unsubscribe / resend — honeypot, time-gate, rate limit
    SeoController.php                        sitemap.xml, /feed, /tr/feed
    PreviewController.php                    Signed-URL preview of unpublished/scheduled articles
    Api/AiImportController.php               AI Import API — validate + store (forced draft), thin over ArticleImportService
  Http/Middleware/AuthenticateAiImportToken.php   Bearer-token auth for the AI Import API (sha256 lookup in api_tokens)
  Livewire/AiAssistantPanel.php              This project's first plain Livewire component (not a Filament Page) — all AI Assistant generate/apply/restore/chat/translate/cancel logic; mounted both inside the Article/Page editor's embedded sidebar and by the standalone AiContentAssistant page — see Section 23
  Jobs/ImportAiArticle.php                   Queued API import — calls the same ArticleImportService::import()
  Jobs/GenerateInternalLinkSuggestions.php   Queued — calls SuggestionEngine::generateAndPersist() (O(n²) scoring, kept off the request cycle)
  Jobs/RunAgentAudit.php                     Queued — calls AgentAuditService::generateAndPersist('manual'), for the AI Agent dashboard's "Run audit now" button; the weekly automatic audit instead runs synchronously via agent:audit, see Section 29
  Jobs/RunAiContentGeneration.php            Queued — thin dispatch wrapper over ContentAssistantService::generate(), $tries=1, with two cancellation checkpoints — see Section 23; on success also syncs generate()'s returned knowledge_entry_ids onto the AiGeneration via the ai_generation_knowledge_entry pivot — see Section 27
  Jobs/ProcessAiChatMessage.php              Queued — classifies a chat message's intent (ContentAssistantService::classifyIntent) and routes it to RunAiContentGeneration, TranslateArticleDraft, or a plain reply — see Section 23
  Jobs/TranslateArticleDraft.php             Queued — builds a real translated draft Article (via ArticleImportService::import()) or Page (direct Eloquent create), always status=draft — see Section 23
  Notifications/WorkflowStageChanged.php, ReviewRequested.php, PublishingCompleted.php, DeadlineApproaching.php   Channel-agnostic (`via()` filtered through NotificationPreference), in-app-only today via `toDatabase()` → `Filament\Notifications\Notification`, see Section 25
  Mail/
    NewsletterVerificationMail.php           Double-opt-in confirmation email (per-subscriber locale)
    NewsletterCampaignMail.php               Queued newsletter email — used by the admin bulk send, base for future campaigns
  Models/
    Article.php                              Bilingual article model, translation self-relation, published/locale scopes, faqs JSON column (per-article FAQ + FAQPage schema)
    Page.php                                 Standalone-page model — same bilingual two-row shape as Article, fully separate from blog
    NewsletterSubscriber.php                 Newsletter subscriber — double opt-in (verified_at), per-row verify/unsubscribe tokens, locale
    ImportLog.php                            Audit row per AI-import/preview attempt (user, provider, format, result, counts, rollback info)
    AiTemplate.php / AiPrompt.php / AiProfile.php   AI Studio records: content skeletons, saved prompts, provider profiles with import defaults
    ApiToken.php                             AI Import API token — sha256 hash stored, plaintext shown once at creation
    SiteSetting.php                          Generic key/value store — the ad hoc CMS backend for homepage/about-page/menu content
    Media.php                                 Media Library (DAM) record — disk_path/url/mime/size + folder_id, alt_text, width/height, webp_path, thumbnail_path, responsive_paths (JSON); `warnings()` and `usages()` are computed, not stored
    MediaFolder.php                          Nested media folder (self-referential `parent_id`); a folder can only be deleted while empty (checked in the app layer, not just the DB)
    Keyword.php                              Target-keyword for an Article/Page (polymorphic `keywordable`) — see Section 22
    InternalLinkSuggestion.php               A persisted "source should link to target" suggestion — pending/approved/dismissed, `origin` rule_based|ai — see Sections 22 and 23
    AiGeneration.php                          One AI Content Assistant run for one field — status/result/input_snapshot for restore, status can also be `cancelled` — see Section 23; also `knowledgeEntries(): BelongsToMany` (`ai_generation_knowledge_entry` pivot) recording which KnowledgeEntry rows were used for this run, see Section 27
    AiChatMessage.php                         One message in a record's AI Assistant chat thread — user|assistant, optionally linked to the AiGeneration it triggered — see Section 23
    AiProviderConfig.php                      One vendor's connection settings — encrypted `api_key`, `is_usable` computed accessor (enabled AND has a key) — see Section 24
    AiProviderModel.php                       Admin-maintained model catalog per provider (label/model ID/optional per-million-token pricing) — see Section 24
    AiActionOverride.php                      Per-ActionRegistry-field provider/model override — unique on `action_key`; no row for a field means "use the default provider" — see Section 24
    AiUsageLog.php                             One row per ProviderManager::respond() call, success or failure — denormalized provider_slug/model (no FK) so deleting a config never breaks usage history — see Section 24
    AiProviderSetting.php                     Singleton settings row (default/failover/fallback provider) — `current()` is the only read path — see Section 24
    AiAuditRun.php                            One AI Agent audit run (manual or scheduled) — status + found/new/resolved counts, see Section 29
    AiRecommendation.php                      One AI Agent finding — pending/applied/rejected, optional one-click-fix routing (`fix_type`/`fix_field`/`fix_mode`), linked to the `AiGeneration` that produced its fix preview, see Section 29
    Tag.php                                   Content-organization tag (auto-slug), `MorphToMany` on Article/Page (and, since Section 27, KnowledgeEntry) via `taggables` — deliberately separate from Keyword, see Section 25
    WorkflowStage.php                         Configurable pipeline stage (label/slug/sort_order/color/is_default/is_terminal/checklist_items JSON) — see Section 25
    ContentPlan.php                           The planner card — can exist with no Article/Page yet, `moveToStage()`/`materializeContent()`, see Section 25
    ContentTask.php                           Per-plan task (title/status/due_at/assigned_to/notes/sort_order) — see Section 25
    ContentPlanStageTransition.php            Immutable per-move audit row, feeds dashboard math (avg publish/review time) — see Section 25
    NotificationPreference.php                Per-user/event/channel opt-out row — no row means enabled — see Section 25
    BrandMemorySection.php                    A labeled slot of brand knowledge (Mission, Forbidden Words, ...) — 25 seeded (is_system=true, undeletable) + admin-addable custom ones — see Section 26
    BrandMemoryValue.php                      One section's content in one language (en/tr/fa — fa is value-only, not a site locale); LogsActivity for version history — see Section 26
    KnowledgeEntry.php                        One retrievable fact/document about the brand (title/category/locale/content/source/status/priority/pinning/expiry), one-row-per-language like Article — not Brand Memory's per-field-per-locale shape; LogsActivity for version history (surfaced in the existing ActivityLogPage, no bespoke history UI) — see Section 27
    KnowledgeEntryAttachment.php              A PDF/document attached to a KnowledgeEntry — plain file storage, deliberately bypasses MediaProcessor (image-only pipeline) — see Section 27
    User.php                                  Default Laravel auth user (admin login)
  Services/ArticleImport/ArticleImportService.php   UI-independent import pipeline (parse → validate → map → import) — the AiImport page and the AI Import API both call the same analyze()/preview()/import()/rollback() methods, now with five auto-detected formats (JSON/Markdown/HTML/XML/custom [[FIELD]] markers) and an $overrides parameter for manual corrections — see Section 28
  Services/Media/MediaProcessor.php          Image pipeline used by every upload path (Media Library, Article/Page featured image): stores the original untouched, generates WebP + thumbnail + responsive WebP variants; `replace()` overwrites content at the *same* disk_path so existing references never break
  Services/Media/MediaUsageScanner.php       Finds where a Media row's disk_path is referenced (Article image_path/body, Page image_path/body, SiteSetting values) — string/LIKE matching, since those fields store raw paths, not foreign keys
  Services/Seo/SeoAuditService.php           SEO Center's audit engine — 9 fast DB-only checks (run() on every page load) + checkExternalLinks()/checkUrls() (real HTTP, manual trigger only) — see Section 8
  Services/Seo/HtmlContentScanner.php        DOMDocument helper shared by SeoAuditService — pulls <a href>/<img alt>/headings/paragraphs out of Article/Page body HTML
  Services/Seo/InternalLinkResolver.php      Classifies/resolves internal hrefs — extracted out of SeoAuditService so Internal Linking Center could reuse it instead of duplicating it — see Section 22
  Services/InternalLinking/LinkGraphService.php    Builds the full internal link graph (nodes + edges, inbound/outbound counts) — see Section 22
  Services/InternalLinking/SuggestionEngine.php    Rule-based internal-link suggestion scoring + persistence — see Section 22
  Services/AiAssistant/Contracts/AiProvider.php    Low-level per-vendor contract (respond()) — unchanged since first introduced — see Section 23
  Services/AiAssistant/Contracts/UsageAwareProvider.php   Optional interface a provider additionally implements to expose lastUsage() (prompt/completion tokens) — checked via instanceof, never required — see Section 24
  Services/AiAssistant/Support/ProviderCredentials.php   Plain readonly DTO (api key/base URL/model/max tokens/temperature/timeout) passed into a dynamically-built provider instance — see Section 24
  Services/AiAssistant/Providers/AnthropicProvider.php, OpenAiProvider.php, GeminiProvider.php, GrokProvider.php, DeepSeekProvider.php, NullProvider.php   The five live vendor providers (OpenAI/Grok/DeepSeek share an OpenAiCompatibleProvider base class) plus the no-key-configured fallback — see Section 24
  Services/AiAssistant/Providers/OpenAiCompatibleProvider.php   Abstract base for the three OpenAI-Chat-Completions-shaped vendors — see Section 24
  Services/AiAssistant/ProviderManager.php   Orchestration layer every AI feature calls instead of an AiProvider directly — resolution (override → default → legacy .env), retry, failover, usage logging, cost estimation, testConnection() — see Section 24
  Services/AiAssistant/ActionRegistry.php    Every generatable field: label, applicable model(s), allowed modes, response shape, prompt instruction — see Section 23
  Services/AiAssistant/ContentAssistantService.php   Builds prompts (each system-prompt builder appends BrandMemoryService::buildContext() via a shared withBrandMemory() helper), calls ProviderManager::respond(actionKey: $field), parses the response — never writes to a record — see Sections 23, 24, and 26. generate() additionally pulls relevant KnowledgeEntry rows via KnowledgeBaseService (skipped for content_review_summary and non-en/tr locales) and returns which entry IDs were used — see Section 27
  Services/AiAssistant/ContentReviewService.php    Deterministic (non-AI) per-record content audit + scoreCard() (AI Health Report, six category scores) — see Section 23
  Services/BrandMemory/BrandMemoryService.php      buildContext(locale): composes every enabled BrandMemorySection into one grouped text block, English fallback per section, empty string when nothing is configured — see Section 26
  Services/AiAssistant/DiffService.php       Self-built, zero-dependency word-level (LCS) diff — red/green preview before any AI suggestion is applied — see Section 23
  Services/AiAssistant/GenerationApplier.php  The shared write path for an AiGeneration's result — extracted from AiAssistantPanel so both the editor sidebar and the AI Agent dashboard call the exact same apply()/restore()/applyInternalLinkSuggestions(), no duplicated write logic — see Section 29
  Services/AiAgent/AgentAuditService.php     Detection engine for the AI Agent dashboard — sixteen categories, mostly thin wrappers over SeoAuditService/LinkGraphService/ContentReviewService, a handful of new small hand-rolled heuristics; generateAndPersist() upserts into ai_recommendations, never touches applied/rejected rows — see Section 29
  Services/AiAgent/AgentFixService.php       queueFix()/approveFix()/rejectFix() for one AiRecommendation — reuses RunAiContentGeneration/TranslateArticleDraft/GenerationApplier, adds no new write path — see Section 29
  Services/KnowledgeBase/KnowledgeBaseService.php   retrieveRelevant(query, locale, limit): pinned entries always included, then a keyword/tag/priority pre-filter shortlists candidates for App\Services\AiAssistant\ProviderManager to rank by true relevance (falling back to the keyword ranking alone if no provider is available or the call fails) — no vector database, see Section 27
  Providers/Filament/AdminPanelProvider.php   Filament panel configuration
config/                                       Standard Laravel config (app, auth, cache, database, filesystems, livewire, logging, mail, queue, services incl. `anthropic`, session)
database/
  migrations/                                users, cache, jobs (Laravel defaults) + articles (incl. faqs + seo_title/meta_description/og_title/og_description columns), media (+ DAM columns: disk, folder_id, alt_text, width, height, webp_path, thumbnail_path, responsive_paths), media_folders, site_settings, activity_log, pages (+ same seo/og columns), newsletter_subscribers, import_logs (incl. rollback columns), ai_templates/ai_prompts/ai_profiles, keywords (polymorphic), internal_link_suggestions (+ origin column), ai_generations (project-specific, dated 2026-07-xx), ai_chat_messages (project-specific, dated 2026-07-16_000017), ai_provider_configs (+ a seed migration inserting the 5 vendor rows), ai_provider_models, ai_action_overrides, ai_usage_logs, ai_provider_settings (all dated 2026-07-16_00001{8-23} — see Section 24), tags + taggables (2026_07_16_000024/025), workflow_stages (+ a seed migration inserting the 8 default stages) + content_plans + content_tasks + content_plan_stage_transitions (2026_07_16_00002{6-9}), notifications (`notifiable_type` explicitly `varchar(100)`, not `morphs()`'s default 255 — same MySQL/utf8mb4 index-key-length lesson as `ai_generations`/`taggables`) + notification_preferences + a `deadline_notified_at` column on content_plans (2026_07_16_00003{0-2}), a fix-up migration for the `notifications` index on installs that hit the key-length error before this fix (2026_07_16_000033) — see Section 25, brand_memory_sections + brand_memory_values + a seed migration inserting the 25 default sections (2026_07_16_00003{4-6}) — see Section 26, knowledge_entries + knowledge_entry_attachments + ai_generation_knowledge_entry (2026_07_16_00003{7-9}) — see Section 27, a fix-up migration giving `ai_generation_knowledge_entry`'s unique index an explicit short name on installs that hit MySQL's 64-character identifier-length error (SQLSTATE 42000/1059) before this fix (2026_07_17_000000) — same key-length lesson as `ai_generations`/`taggables`/`notifications` above, missed for this one table when it first shipped, ai_audit_runs + ai_recommendations (2026_07_17_00000{1,2} — the latter's unique index is explicitly named `ai_recommendation_unique` from the start, applying that same lesson proactively) — see Section 29
lang/
  en/newsletter.php, tr/newsletter.php       ALL user-facing newsletter strings (form messages, result pages, emails) — the only lang files in the project
  factories/, seeders/
resources/
  views/
    home.blade.php, about.blade.php, blog.blade.php, blog-post.blade.php, page.blade.php     English public pages
    tr/home.blade.php, tr/about.blade.php, tr/blog.blade.php, tr/blog-post.blade.php, tr/page.blade.php   Turkish duplicates
    layouts/master.blade.php, layouts/master-tr.blade.php                    Shared page shell per locale (head/meta/CSS/header/footer)
    welcome.blade.php                        Default Laravel scaffold view — UNUSED, not routed to. Safe to delete; do not build on it.
    filament/pages/                           Custom Filament page Blade views (homepage-settings, about-page-settings, menu-settings, footer-settings, activity-log, media-library, seo-center, internal-linking-center, ai-content-assistant, system-maintenance, content-planner, editorial-calendar-redirect, brand-memory) — ai-content-assistant.blade.php is now a thin `@livewire('ai-assistant-panel', [..., 'standalone' => true])` shell; the old editorial-calendar.blade.php was deleted (dead code) once EditorialCalendar became a redirect, see Section 25
    filament/pages/partials/brand-memory-history.blade.php   Version-history list + Restore buttons, included from both the history panel and re-rendered on demand — see Section 26
    filament/resources/articles/pages/edit-article.blade.php, filament/resources/pages/pages/edit-page.blade.php   Custom EditRecord views (override `$view` on EditArticle/EditPage) — wrap `{{ $this->content }}` in a two-column layout with the embedded AI Assistant sidebar/drawer — see Section 23
    livewire/ai-assistant-panel.blade.php     The shared AI Assistant markup — mounted by both the embedded sidebar and the standalone page — see Section 23
  css/app.css, js/app.js                      Vite entry points (Tailwind + minimal JS)
routes/
  web.php                                     All public + admin-adjacent routes (see Security Rules — contains two routes that must be fixed)
  api.php                                     AI Import API only (token-authenticated, forced-draft policy)
  console.php                                 Artisan closures + the schedule definition
public/                                       index.php, favicon, robots.txt, compiled Filament vendor assets
tests/
  Feature/ExampleTest.php, Unit/ExampleTest.php   Default Laravel scaffold tests only — NOT project-specific coverage (see Testing Strategy)
  Feature/AiAssistantPanelTest.php           Sidebar-redesign coverage (Section 23): DiffService, ContentReviewService::scoreCard(), the body field, Quick Actions/Optimize Entire Article, AI Chat + ProcessAiChatMessage, Translate + TranslateArticleDraft, cancellation, History — kept separate from AiContentAssistantTest.php, which still covers the original ActionRegistry/ContentAssistantService/job/provider layer unchanged
  Feature/TagsTest.php, ContentPlanTest.php, EditorialNotificationsTest.php, WorkflowStageResourceTest.php, ContentPlanResourceTest.php, ContentPlannerKanbanTest.php, ContentPlannerCalendarTest.php, ContentPlannerTableDashboardTest.php, ContentPlannerAiIntegrationTest.php   Editorial Workflow & Content Planner coverage — see Section 25
  Feature/AdminPanelResilienceTest.php      Asserts the admin panel (incl. System Maintenance) stays reachable even before this feature's migrations have run — see Section 25's `databaseNotifications()` guard
  Feature/BrandMemoryTest.php, BrandMemoryPageTest.php   Brand Memory coverage — see Section 26
  Feature/KnowledgeBaseTest.php, KnowledgeBaseRetrievalTest.php, KnowledgeBaseGenerationIntegrationTest.php, KnowledgeEntryResourceTest.php   Knowledge Base coverage — see Section 27
  Feature/KnowledgeBaseMigrationFixTest.php   Asserts the `ai_generation_knowledge_entry` pair is actually unique at the DB level and that the `2026_07_17_000000` fix-up migration is idempotent — see Section 27
  Feature/AiAgentTest.php, AiAgentDashboardTest.php   AI Agent coverage — every detector category, generateAndPersist()'s upsert/never-touch-decided-rows/stale-cleanup invariants, AgentFixService's three fix_type branches, the agent:audit command/RunAgentAudit job, and the dashboard's Livewire behavior — see Section 29
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
- Custom (non-CRUD) admin screens are Filament **Pages**, not Resources — `EditorialCalendar`, `HomepageSettings`, `AboutPageSettings`, `MenuSettings`, `FooterSettings`, `ActivityLogPage`, `AiImport` are all `Pages` with a paired Blade view under `resources/views/filament/pages/`. Use this pattern for any new admin screen that isn't a straightforward CRUD resource.
- **AI Studio** (nav group): `AiImport` page ("One Click Publish" — see Section 28 for the full feature), `DraftQueue` page, `ImportLogResource` (Import History), `AiTemplateResource`, `AiPromptResource`, `AiProfileResource` — all backed by `App\Services\ArticleImport\ArticleImportService`. `AiProviderConfigResource` (nav "AI Providers") and `AiActionRouting` page (nav "AI Routing") are also in this group — see Section 24 for what each does; `BrandMemory` page (nav "Brand Memory") is also in this group — see Section 26 — and is conceptually orthogonal to `AiProfileResource`: `AiProfile` fills blank *import-time* fields (language/status/category/author) on a manually-pasted article, it never touches a live AI prompt, whereas `BrandMemory` is read by every live AI generation call. Don't conflate the two or merge their nav entries. `AiUsageLogResource` (nav "AI Usage Logs") is the read-only usage-history counterpart to `ImportLogResource`, same "canCreate/canEdit/canDelete all false, filters + CSV export" shape. `AiAgentDashboard` (nav "AI Agent", last in this group's nav order) is the proactive audit dashboard — see Section 29; unlike every other AI Studio screen it doesn't need a specific record to open (no `?article=ID` deep-link pattern), it scans the whole site. `AiContentAssistant` (Section 23) is also nav-grouped under "AI Studio" but is the one page in the group with `shouldRegisterNavigation()` returning `false` — it needs a specific Article/Page record; it now exists purely as a standalone full-page fallback (`?article=ID`/`?page=ID`) rather than the primary way admins reach the assistant, which is the embedded editor sidebar (see the `AI Content Assistant` bullet below and Section 23). All parse/validate/map/persist/rollback logic lives in the service, NOT the pages — the AI Import API (`AiImportController`) calls the same `analyze()`/`preview()`/`import()`/`rollback()` methods rather than duplicating any of it, and `App\Jobs\TranslateArticleDraft` (Section 23) calls `import()` directly for its Article path too. `analyze()` never writes; `preview()` writes only a `previewed` history row; `import()` is the only article-creating path and always records an `ImportLog` row (success or failure); `rollback()` deletes the imported article via Eloquent (so the activity log records it) and stamps `rolled_back_at/by` on the log — now reachable from a "Roll back" button in the `AiImport` page's Recent Imports table, see Section 28. AI Profile defaults fill only fields the pasted content leaves empty (content always wins); the newer manual-corrections `$overrides` (Section 28) always win, even over non-empty content.
- **Media Library** (`MediaLibrary` page, nav item "Media Library"): a fully custom Livewire screen (folders sidebar, drag-and-drop/multi-file upload zone, search + type/unused/missing-ALT/large-file filters, grid with warning badges, a details panel for preview/ALT/folder-move/usage/replace/delete) — not a Filament Resource table, because the folder/grid/drag-drop UX doesn't fit that shape. Follows `EditorialCalendar`'s established pattern for custom interactive admin screens: plain vanilla JS in a `<script>` tag at the bottom of the Blade view calling `@this.uploadMultiple(...)`/`@this.upload(...)` (Livewire's documented JS upload API) for the dropzone and the single-file "Replace" input. Because the details panel (and other elements) are conditionally rendered via `@if`, re-wiring those JS handlers cannot rely on Livewire's `morph.updated` hook alone (that only fires for updates to *existing* nodes, not newly-added ones) — a `MutationObserver` on `document.body` re-runs the wiring after any DOM change instead; the wiring functions are idempotent (property assignment, not `addEventListener`) so re-running them is always safe. All image processing goes through `MediaProcessor` (see Image Optimization Rules) — the page never touches Intervention Image directly. Any Blade comment inside a `<script>` block in this file must avoid the literal characters `@if`/`@foreach`/etc. (even inside a JS `//` comment) — Blade compiles the whole file textually before it reaches the browser, so a Farsi comment that happens to contain `@if` as English scaffolding breaks compilation.
- **SEO Center** (`SeoCenter` page, nav item "SEO Center"): another fully custom Livewire screen (sidebar of 10 issue categories with counts, filter toolbar, findings table, CSV export) — read-only, no drag-drop/upload, so it's simpler than Media Library but follows the same "custom Page, not a Resource" precedent, and the same inline `<style>`-in-Blade convention as `EditorialCalendar`/`MediaLibrary` rather than a component library. All audit logic lives in `App\Services\Seo\SeoAuditService` (see Section 8) — the page class is thin: it holds Livewire filter state, calls the service, and streams CSV downloads via `response()->streamDownload()` returned directly from a `wire:click`-bound method (Livewire 3's documented file-download-from-action pattern). `MediaLibrary::mount()` was extended to read an optional `?media=ID` query string and pre-select that item (opening its folder) — this is how SEO Center's "Missing ALT Text" findings deep-link into the DAM for a Media-backed image; reuse this same query-param convention for any other page that needs to deep-link into the Media Library, rather than inventing a second selection mechanism.
- **Internal Linking Center** (`InternalLinkingCenter` page, nav item "Internal Linking"): a third custom Livewire screen, one step more complex than SEO Center — three tabs (`$activeTab`: dashboard/suggestions/graph) inside a single page rather than three separate nav items, following the same "don't proliferate nav items for one feature" instinct as grouping AI Studio's screens. The dashboard tab is a near-exact copy of SEO Center's sidebar-categories-plus-table layout (same `ilc-*` CSS class names mirroring SEO Center's `seo-*` ones) — reuse that structure for any future audit-style page rather than inventing a fourth variant. See Section 22 for what each tab actually does and which parts reuse `SeoAuditService`.
- **AI Content Assistant** — the AI generate/improve/review/chat/translate logic lives in `App\Livewire\AiAssistantPanel`, this project's **first plain Livewire component** (`extends \Livewire\Component`, not a `Filament\Pages\Page`). It's mounted in two places with the same component class and Blade view (`resources/views/livewire/ai-assistant-panel.blade.php`): (1) embedded directly in `EditArticle`/`EditPage` via a custom `$view` override (`resources/views/filament/resources/articles/pages/edit-article.blade.php` and the `pages/pages/edit-page.blade.php` equivalent) that wraps `{{ $this->content }}` in a two-column layout — a collapsible right sidebar on desktop, a bottom drawer below `1024px`, Alpine.js state persisted to `localStorage`, toggled by a header `Action::make('aiAssistant')` that dispatches a `toggle-ai-sidebar` browser event (`$this->dispatch(...)`) rather than navigating away; (2) the thin `App\Filament\Pages\AiContentAssistant` page (`mount()` reads `?article=ID`/`?page=ID`, 404s if neither resolves) kept live as a standalone fallback/deep link, passing `standalone=true` so its "Back to editing" button and full-width layout render. Same `ai-ca-*`-prefixed inline `<style>`-in-Blade convention as the other custom screens, now with `:root.dark` overrides (Filament's actual dark-mode toggle mechanism — **not** `prefers-color-scheme`, which doesn't track it) since this is the first AI Studio screen styled for dark mode. Uses `$activeTab` (generate/review/history) and this project's first conditional `wire:poll` (rendered while any generation is queued/processing, or a chat reply is pending). See Section 23 for the full feature — sidebar embedding, AI Chat, diff preview, health score card, Quick Actions, Translate, and cancellation.
- **Content Planner** (nav group): `TagResource` (nav "Tags"), `WorkflowStageResource` (nav "Workflow Stages"), `ContentPlanResource` (routable Create/Edit only, `shouldRegisterNavigation() => false`), and `ContentPlanner` (nav "Planner") — the group's actual entry point, one Page with four switchable views (Kanban/Calendar/Table/Dashboard) following the same "one Page, several tabs" precedent as SEO Center/Internal Linking Center/AI Content Assistant. `EditorialCalendar` still exists (its old URL must keep working) but is `shouldRegisterNavigation() => false` and does nothing but redirect into this group's Calendar view. See Section 25 for the full feature.
- **Knowledge Base** (its own nav group, deliberately not nested under "AI Studio"): a single ordinary CRUD resource, `KnowledgeEntryResource` (nav "Knowledge Entries") — unlike Media Library/SEO Center/Internal Linking Center/Content Planner, this needed no custom Livewire page; a standard `Schemas/{Name}Form.php` + `Tables/{Name}sTable.php` + `Pages/` layout (mirroring `TagResource` exactly) was enough. See Section 27 for the full feature and why it's read automatically by every AI generation call rather than needing its own "use this" button.
- `HomepageSettings`, `AboutPageSettings`, `MenuSettings`, and `FooterSettings` manually decode/encode JSON and normalize Filament's `FileUpload` return shape (e.g. `array_values(array_filter($value))[0] ?? null`) in `mount()`/`save()`. This is brittle by nature (tied to Filament's current return shape) — if a Filament upgrade changes `FileUpload`'s value shape, check these files first.
- Layout-level CMS data (header menu `menu.{locale}.items`, footer `footer.{locale}.*`) is loaded **inline in the master layouts** via `@php` + `SiteSetting` (the footer block sits before the `<style>` tag because the background image is used inside the CSS) — not via controllers or view composers. Follow that in-layout pattern for any future layout-level content.
- Repeater items that need manual ordering (e.g. `AboutPageSettings`'s `certificates`/`gallery`/`timeline`) get an explicit numeric `sort_order` field alongside Filament's built-in `->reorderable()` drag handle, and the consuming controller sorts by it (`BlogController::sortBySortOrder()`) before passing data to the view. Reuse this pattern — plain numeric field + a small `usort` in the controller — rather than relying on array order alone, since admins may want to reorder without dragging.
- Fallback/default content for a CMS-managed page lives in the **Blade view**, not the Filament Page class — `mount()` loads raw `SiteSetting` values (null if unset) exactly like `HomepageSettings`, and the public template supplies the current design's copy as the second argument to a `$v($key, $default)` helper closure (see `home.blade.php`/`about.blade.php`). This means a freshly-added content block renders identically to today until an admin actually edits it in `/admin` — do not pre-fill defaults in the Filament form itself.
- Every admin-facing label must stay in **plain English understandable by a non-developer business owner** (Ehsan). Do not expose internal field names, JSON structures, or developer jargon in any Filament label/helper text — follow the existing tone in `ArticleForm` (e.g. "Publish date" with helper text "For 'Scheduled': set a future date/time — the article goes live automatically at that moment.").
- `ArticleForm`'s slug auto-fill (`afterStateUpdated` on `title` → `Str::slug` into `slug`) is the standard pattern for any future slug-bearing resource — reuse it rather than inventing a new slugging approach.
- Featured images (`ArticleForm`/`PageForm`, field `image_path`) use Filament's `FileUpload` with `->saveUploadedFileUsing(...)` overridden to call `MediaProcessor::store()` instead of Filament's default save — this is what makes every article/page featured image a real Media Library row (WebP/thumbnail/responsive generated, ALT-editable, usage-tracked, delete-guarded) instead of an untracked file. The returned value is still just the plain disk-relative path (`$media->disk_path`), so `image_path`'s stored format and every existing reader of it (`BlogController`, blade views, `SeoController`) is unchanged. Follow this same `saveUploadedFileUsing` + `MediaProcessor::store()` pattern for any new image field that should be DAM-managed — see Image Optimization Rules below.

## 7. Multilingual Architecture

- Two locales today: `en` (default) and `tr`. Translation of **page content** is handled entirely by **duplicating routes, controller methods, views, and database rows** per locale, not by string-translation files. Do not introduce Laravel's `__()` translation-file system for page content; it does not fit this project's model (content is data-driven from the database, not static UI strings).
- **`fa` (Persian) exists only as a `BrandMemoryValue` locale (Section 26), not a site content locale.** An admin can write brand knowledge in Persian for the AI to read as reference material, but there are no `/fa` routes, no `Article`/`Page` Persian support, and `fa` is not a `translate` target — this was an explicit, confirmed scope decision. Do not treat the presence of `fa` in `BrandMemory::LOCALES` as a first step toward a third site locale; that would be a separate, much larger, explicitly-scoped decision (see Important Project Decisions and Future Development Guidelines).
- Exception for **module UI strings** (form feedback messages, transactional email copy): the newsletter module keeps all its user-facing strings in `lang/en/newsletter.php` + `lang/tr/newsletter.php` and resolves them with `__()` after `app()->setLocale()` per request (or `Mailable::locale()` per email). Follow this pattern for any future module that needs small sets of fixed UI strings in both languages — it is not a license to move page content into lang files.
- Route convention: English routes are bare (`/`, `/blog`, `/blog/{slug}`), Turkish routes are prefixed with `/tr` (`/tr`, `/tr/blog`, `/tr/blog/{slug}`). Follow this exact prefix convention for any new localized route — do not use a `{locale}` route parameter or subdomain-based localization; it would require a larger refactor and hasn't been decided.
- Standalone CMS pages (the `Page` model — privacy, terms, FAQ, ...) resolve at root level: `/{slug}` and `/tr/{slug}`. These two routes are registered **last** in `routes/web.php` with a reserved-slug lookahead (admin/blog/about/tr/feed/...) so every other route always wins — keep them at the bottom of the file, and register any new fixed route above them.
- Article rows carry their own `locale` column and are queried with `Article::locale('en')` / `Article::locale('tr')`. The `translation_of` foreign key links a translated pair together (see Laravel Conventions above) — always set this when creating a translated counterpart of an existing article, so `BlogController::renderShow()` can surface the "other language" link.
- `hreflang` tags are present in `master.blade.php` but **commented out** with the note "فعال‌سازی بعد از آماده شدن نسخهٔ ترکی" ("enable after the Turkish version is ready"). Do not silently re-enable these — see Important Project Decisions.
- When adding a new public page, always create both the `en` and `tr` versions together in the same change, plus both route registrations. Never ship an English-only page as "TR to follow later" — that is exactly the pattern that has caused drift in the past (git history shows CSS/layout fixes landing for one locale before the other).

## 8. SEO Rules

- Every public page must set, via `@section`/`@yield` in the master layout: `title`, `meta_description`, `canonical`, and Open Graph (`og_title`, `og_description`, `og_type`). Never leave a new page on the master layout's generic defaults — write page-specific copy.
- `robots` meta is `index,follow` globally (`master.blade.php`) — there is no per-page noindex mechanism today. If a page should ever be excluded from indexing (e.g. a preview page), do not rely on this meta tag; the `/preview/article/{article}` route already protects itself via Laravel's `signed` URL middleware instead, which is the correct approach for non-public pages — prefer signed/authenticated access over `noindex` for anything that must not be publicly discoverable.
- `sitemap.xml` (`SeoController::sitemap`) and RSS feeds (`/feed`, `/tr/feed`) are generated **dynamically from the database on every request** — they are always in sync with published articles by construction. Do not switch these to a cached/static file unless a real performance problem is measured; freshness here has been an explicit design goal (see the Farsi comment in `SeoController`: "نقشه‌ی سایت داینامیک — همیشه به‌روز").
- `SeoController::sitemap()` currently includes only `/`, `/about`, `/blog`, `/tr`, `/tr/about`, `/tr/blog` as static URLs plus all published articles. When adding a new static public page, add its URL (both locales) to this list.
- JSON-LD structured data has a dedicated `@yield('json-ld')` slot in the master layout, populated today on `home.blade.php`/`tr/home.blade.php` (`Organization` + `Person` graph), `about.blade.php`/`tr/about.blade.php` (`Person` schema), and `blog-post.blade.php`/`tr/blog-post.blade.php` (`Article` schema, plus `FAQPage` when the article has FAQs) — the blog **index** (`blog.blade.php`/`tr/blog.blade.php`) and standalone `Page` records (`page.blade.php`/`tr/page.blade.php`) still use the layout's empty default and have no schema at all. The **SEO Center** (`SeoCenter` Filament page, see below) surfaces this gap under "Missing Schema" — check there before assuming a page is covered. When adding structured data to a new page, inject it through this slot — do not hand-write `<script type="application/ld+json">` blocks elsewhere in the templates.
- `og:image` support exists via `@yield('og_image')` in both master layouts (rendered only if non-empty, via `$__env->yieldContent('og_image')` — see `master.blade.php`). The About page sets it from `about.{locale}.seo_og_image` (a Filament `FileUpload`, falls back to nothing if unset). Wire up the same `@section('og_image', ...)` pattern for any other page that gets a manageable OG image (e.g. blog posts, using the article's `image_path`) rather than inventing a second mechanism.
- **SEO Center** (`SeoCenter` Filament page, nav item "SEO Center"): a read-only audit dashboard over everything described above — it does not replace or duplicate any of the mechanics in this section, it only *reports* on the data those mechanics already consume (`Article`/`Page` rows, `SiteSetting`-driven menu/footer, the Media Library). All checks live in `App\Services\Seo\SeoAuditService` (fast, DB-only checks — titles/descriptions/canonicals/schema/duplicates/internal-links/orphans, re-run automatically every time the page loads) and its `checkExternalLinks()` method (real HTTP HEAD requests via `Http::pool()`, deliberately **not** run automatically — only on the "Scan external links" button — since it's the one check with real network latency). `App\Services\Seo\HtmlContentScanner` is the shared DOMDocument-based helper that both link-checking and ALT-text checks use to read `<a href>`/`<img alt>` out of `Article.body`/`Page.body`. Concretely, per category:
  - *Missing Meta Titles / Missing Meta Descriptions*: checked on `Article`/`Page` only (`title` blank, or the effective description — `Article.excerpt` if set, else `strip_tags(body)` for both models, matching what the public templates actually render — shorter than 50 characters). Home/About are intentionally excluded from these two checks (and from Duplicate Titles/Descriptions) because their titles/descriptions are hand-authored fallback copy in Blade/`SiteSetting`, not a repeated per-record field, so "missing/duplicate" isn't a meaningful signal there.
  - *Missing Canonicals*: always reports zero findings, on purpose — every route in this app gets a canonical from `master.blade.php`'s `@yield('canonical', url()->current())` fallback (see above), so there is structurally nothing to find. The check stays in the code as a tripwire in case that fallback is ever removed.
  - *Missing ALT Text*: merges two sources — inline `<img>` tags inside `Article`/`Page` bodies (via `HtmlContentScanner`, since these were never registered as `Media` rows) and Media Library rows (`Media::alt_text` blank) that are actually `isInUse()` per `MediaUsageScanner` (an unused DAM asset missing ALT text is already surfaced by the Media Library's own "Missing ALT" filter — repeating it here would be noise).
  - *Missing Schema*: `Article` is never flagged (schema is unconditional in `blog-post.blade.php`); every `Page` row and the blog index (both locales) are always flagged, since those templates emit no JSON-LD at all — this is a template-level gap, not a per-record content problem, so the finding says so explicitly instead of implying "just edit this page".
  - *Broken Internal/External Links* and *Orphan Pages*: built from the same internal link graph — `Article.body`, `Page.body`, `menu.{locale}.items[].url`, and `footer.{locale}.columns[].links[].url`. A link is "internal" if its host matches `config('app.url')`'s host (never hardcode the domain — see Things That Must Never Be Changed). Internal target resolution mirrors `routes/web.php` exactly (`SeoAuditService::internalPathExists()`) — if that file's route list changes, update this method too. A published `Article`/`Page` is "orphan" if its own path never appears as a link target anywhere in that same graph; draft/scheduled items are never flagged as orphans (they aren't expected to be linked yet). Every `Page` orphan finding also notes that standalone pages aren't in `sitemap.xml` either (see above) — that's existing, documented behavior being surfaced, not a new gap this feature introduces.
  - Every finding carries a locale, a type (`Article`/`Page`/`Media`/`Menu`/`Footer`/`Blog index`), and an `edit_url` (Filament resource/page `getUrl()`) so the dashboard's "Fix it" column links straight to the right screen — `Media` findings deep-link into the Media Library via `MediaLibrary::getUrl(['media' => $id])`, which `MediaLibrary::mount()` reads to pre-select that item and open its folder.
  - Filtering (locale/type/free-text search) and CSV export (current filtered view, or a full all-categories report) are Livewire-only — no new database table, no persisted scan history; re-running the audit is cheap enough at this project's content volume that there's nothing to cache (see Performance Rules). If it's ever asked to remember external-link scan results across page loads, that's the point where a small `seo_link_checks` table would become justified — not before.

## 9. AI Search Optimization

The site's content (self-defense/BJJ instruction, articles) is exactly the kind of material AI answer engines (ChatGPT browsing, Perplexity, Google AI Overviews, Claude) surface when it is structured for extraction. Rules for this project specifically:
- Keep article content in clean, semantic HTML from the Filament `RichEditor` (`ArticleForm::body`) — real `<h2>`/`<h3>` headings, real `<ul>`/`<ol>` lists, no content locked behind JS-only rendering. Since this site is already server-rendered Blade with no client-side rendering gate, this is preserved by default — do not introduce a JS-rendered content path that would hide article text from non-JS crawlers.
- The JSON-LD `@yield('json-ld')` slot is already populated with `Article` schema on `blog-post.blade.php`/`tr/blog-post.blade.php`, `Person` schema on `about.blade.php`/`tr/about.blade.php`, and an `Organization`+`Person` graph on `home.blade.php`/`tr/home.blade.php` — the home page is **not** missing structured data (an earlier version of this file incorrectly claimed it was; verify claims like this against the actual Blade file before repeating them). The blog index and standalone `Page` records genuinely have none — see Section 8's SEO Center note and the "Missing Schema" category it reports.
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
- Do not add dependencies (Composer or npm) for something a few lines of first-party code can do — this project has stayed intentionally dependency-light (5 runtime Composer packages — `filament/filament`, `laravel/framework`, `laravel/tinker`, `spatie/laravel-activitylog`, and `intervention/image` for the Media Library's WebP/thumbnail/responsive pipeline, see Image Optimization Rules — no JS framework). Any new dependency should be justified against that baseline the way `intervention/image` was: a concrete, requested capability plain PHP/GD calls would otherwise reinvent badly.

## 14. Development Principles

- **Never make assumptions.** If the current behavior, data shape, or intent of a piece of code is unclear, read the code (and this file) until it is clear, or ask — do not guess and proceed.
- **Always inspect the existing implementation before changing code.** Read the relevant controller/model/view/Filament class in full before editing it; this codebase has non-obvious intentional behavior (e.g. the `scopePublished()` scheduler safety net, the schemaless `SiteSetting` store) that is easy to break by pattern-matching from a different codebase's conventions.
- **Prefer incremental improvements over large refactors.** Fix the specific thing asked; do not use a task as cover to reshape adjacent code, restructure folders, or "clean up while I'm in here" unless that cleanup was requested.
- **Never rewrite working code without measurable benefit.** "This could be written more elegantly" is not sufficient justification to touch code that is functioning correctly — rewrite only when there's a concrete bug, performance measurement, or explicit request behind it.
- **Explain architectural trade-offs before implementing them.** When a change has more than one reasonable approach (e.g. where to cache, whether to add a dependency, how to structure a new bilingual feature), lay out the options and trade-offs for the user before writing code, rather than silently picking one.
- **Preserve backward compatibility whenever possible.** Don't change route URLs, database column meanings, Filament field keys/`SiteSetting` key names, or public method signatures in ways that would silently break existing content, bookmarked URLs, or in-progress admin edits, unless the change is explicitly about that.

## 15. Security Rules

**Resolved (2026-07-16):** `routes/web.php` used to define two **completely unauthenticated** routes (`/system-cache-flush-7k2p9x`, `/system-migrate-9x4kq2`) added as temporary deploy-ops helpers for a host with no SSH access. They have been removed and replaced with `App\Filament\Pages\SystemMaintenance` (nav item "System Maintenance" in `/admin`) — same two operations (run pending migrations, clear cache), but gated behind Filament's own admin login instead of a bare public GET, since the panel's `authMiddleware` already covers every discovered Page with no extra wiring needed. **Do not re-add a public, unauthenticated maintenance route** — if a deploy-ops need comes up again (e.g. an operation the panel can't reach), extend `SystemMaintenance` or gate a new route behind `auth`/`signed`, never a bare public GET.

`SystemMaintenance::runMigrations()` also self-heals a real failure mode observed on this project's production host: on constrained shared hosting with no SSH, `Artisan::call('migrate')` runs synchronously inside the Livewire request, and a `CREATE TABLE` migration can succeed while the process is killed (timeout/resource limit) before Laravel logs it in the `migrations` table — the table then exists but every future `migrate` attempt fails with "already exists" (SQLSTATE 42S01/1050 on MySQL) and there's no SSH to fix the `migrations` table by hand. The method catches that specific `QueryException`, parses the table name out of the failing `CREATE TABLE` SQL, finds the matching `*_create_{table}_table` migration file, marks it as run, and continues with the rest — this is a deliberate resilience mechanism for this project's specific hosting constraint (same spirit as `scopePublished()`'s scheduler fallback), not speculative error handling. Preserve it; don't simplify it back to a bare `Artisan::call('migrate')`.

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
5. `php artisan migrate --force` **run manually via the deploy process/SSH**, or — on the hosting setup this project actually runs on today, which has no SSH access — via the "System Maintenance" page in `/admin` (see Security Rules). Either way, never via a public unauthenticated route. **A deploy that ships a new migration is not finished until this step runs** — code and schema are deployed separately in this project's manual process, and a page that queries a table from a migration that hasn't run yet will 500 (this has happened in practice: the Internal Linking Center's `keywords` table).
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache` for production performance.
7. Confirm the scheduler cron (`* * * * * php artisan schedule:run`) is present on the server so `articles:publish-due` actually fires every 5 minutes — this is invisible if missing (articles just silently never auto-publish).
8. The admin "Send newsletter" bulk action queues its mails (`QUEUE_CONNECTION=database`) — a queue worker (`php artisan queue:work`, or `queue:run` via cron) must be running on the server or queued newsletters sit in the `jobs` table forever. Verification emails are sent synchronously and do not need the worker. A real `MAIL_MAILER` (SMTP etc.) must be configured in the server `.env` — the repo default is `log`, which writes emails to the log file instead of sending them. The AI Import API's `?queue=1` mode uses the same worker — without it, queued imports also wait in the `jobs` table (non-queued API imports run synchronously and are unaffected).
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
- `ContentPlanner`'s Calendar view (Filament page, superseding the old standalone `EditorialCalendar` — see Section 25) lets Ehsan drag an article to a new date on a calendar to reschedule it — this writes to the same `published_at`/`status` fields, nothing calendar-specific in the schema.
- When editing this pipeline, preserve both halves (the scope's time-based fallback, and the command's active flip + cache clear) — removing either changes observable behavior (removing the scope fallback means a stalled scheduler hides due content indefinitely; removing the command's cache clear means published content is delayed until the next natural cache expiry).
- Every article change is recorded via `spatie/laravel-activitylog` (title/locale/status/published_at/category, dirty-only) and visible in the Filament `ActivityLogPage` — don't bypass Eloquent (e.g. raw DB updates) for article status changes, or this audit trail silently breaks.
- `PublishDueArticles` also advances any `ContentPlan` linked to a just-published article (via `Article::contentPlan()`) to the `published` workflow stage and fires the `PublishingCompleted` notification — a small, deliberate addition to this command, not a second poller; the command remains the single source of truth for "did this actually publish" (see Section 25).

## 21. Image Optimization Rules — Media Library (DAM)

An upload-time image pipeline exists via `App\Services\Media\MediaProcessor`, backing the **Media Library** admin screen (`MediaLibrary` page — folders, drag-and-drop multi-upload, search/filters, preview, usage tracking, replace, delete-guard) and the `image_path` field on `ArticleForm`/`PageForm`. This is real, shipped behavior — not a plan:
- **What gets generated, automatically, on every upload**: the original file is stored **untouched** (never resized/recompressed/deleted — "keep original files" is a hard rule, `replace()` is the only thing that overwrites original bytes, and only because the admin explicitly chose to replace that asset), plus a full-size WebP (quality 82), a 320px-wide WebP thumbnail (quality 75, used in the Media Library grid), and WebP variants at 1600/1200/800/480px width (quality 80) — only for widths smaller than the original, so a 400×300 upload gets no responsive variants and that's correct, not a bug. All derivative paths are stored on the `media` row (`webp_path`, `thumbnail_path`, `responsive_paths` JSON) — nothing is regenerated on read.
- **`Media` folders are real and nested**: `MediaFolder` is self-referential (`parent_id`), navigated via breadcrumb + subfolder chips in the Media Library UI. A folder can only be deleted while empty (`MediaFolder::isEmpty()`, checked in the app layer) — deleting a folder never deletes media or cascades to non-empty children.
- **Usage tracking has no foreign key to lean on**: `Article.image_path`/`body`, `Page.image_path`/`body`, and `SiteSetting.value` all store the plain disk-relative path as a raw string (not a `media.id` reference) — this predates the DAM and hasn't been changed (see Things That Must Never Be Changed). `MediaUsageScanner::scan()` therefore does a `LIKE '%path%'` match against those columns to answer "where is this used" — cheap at this project's content volume (see Performance Rules), but it means usage detection only works for a `Media` row whose exact disk_path is referenced elsewhere; a file that exists on disk but was never registered as a `Media` row (or was uploaded through a *different* `FileUpload` field that isn't wired to `MediaProcessor` — see below) won't show real usage data.
- **Not every image field is DAM-managed yet.** Only `ArticleForm`/`PageForm`'s `image_path` (via `saveUploadedFileUsing`) and the Media Library's own uploader create `Media` rows. `HomepageSettings`, `AboutPageSettings`, `MenuSettings`, and `FooterSettings`'s `FileUpload` fields (hero images, footer background/logo, etc.) still upload directly to disk exactly as before this feature — deliberately not touched, to avoid widening this change's blast radius across every CMS screen. If asked to extend DAM management to those fields, wire their `FileUpload::make(...)` the same way `ArticleForm` does (`->saveUploadedFileUsing(fn ($component, $file) => app(MediaProcessor::class)->store($file, $component->getDirectory(), $component->getDiskName())->disk_path)`) — do not invent a second image-processing path.
- **`php artisan media:backfill`** (`BackfillMediaLibrary`) is a one-off command that registers pre-DAM `Article`/`Page` featured images (files that exist on disk but have no `Media` row yet) into the library, generating derivatives for them too. Run it once after deploying this feature; it's idempotent (skips paths that already have a `Media` row or whose file is missing).
- **Warnings are computed, not stored**: `Media::warnings()` checks missing ALT text, file size > 500KB ("large"), and dimensions > 2000px ("oversized") or < 200px ("very small, likely pixelated as a featured image") every time it's called — there is no `is_flagged` column to go stale. Thresholds live as constants on `Media` — adjust them there, not by adding a config option nobody will find.
- Static design images (hero, about section, footer background, logos) are still committed directly under `public/` and referenced by fixed path — unaffected by any of the above, and out of scope for the DAM (they're not admin-uploaded content).
- Dependency: `intervention/image` (^3.0, GD driver — this host has the `gd` PHP extension, not `imagick`) was added specifically for this pipeline; it was the option this file already flagged as the right one when this gap eventually got filled (see the old text of this section in history) — do not swap drivers or add a second image library without a concrete reason.
- Processing is **synchronous**, inside the same Livewire request that handles the upload (a few hundred ms for a handful of GD resizes) — not queued. This project's queue worker isn't guaranteed to be running on every deploy (see Deployment Workflow), so making this queue-dependent would risk uploads silently sitting unprocessed; revisit only if upload volume/size grows enough to make the admin UI upload feel slow.
- Prefer serving modern formats (WebP) with the original as fallback — that's what `Media::thumbnail_url`/`webp_url`/`responsive_urls` accessors are for. Public-site templates (`blog-post.blade.php` etc.) do **not** currently consume these — they still render the plain original via `asset('storage/'.$article->image_path)`, unchanged, per Brand Identity's "never redesign UI without explicit approval" (those templates use CSS `background-image`, not `<img srcset>`, so wiring this in is a real markup decision, not a drop-in). Wiring responsive/WebP delivery into the public templates is a good candidate for a future, explicitly-scoped change — see Future Development Guidelines.

## 22. Internal Linking Center

A dedicated Filament page (`InternalLinkingCenter`, nav item "Internal Linking") for internal-linking SEO health — a dashboard, a rule-based link-suggestion engine, and a link graph visualization. Like the SEO Center, it **reads and reports on** the data other systems already own; it does not replace `SeoAuditService`, the DAM, or the multilingual architecture. Concretely:
- **Dashboard categories, and where each one actually comes from** (do not duplicate any of these elsewhere):
  - *Orphan Articles* / *Orphan Pages* — both are `SeoAuditService::run()['orphan_pages']` (published, zero inbound links, per the same link graph as SEO Center), split into two dashboard cards by `type` for display only. The underlying computation is called once, not twice.
  - *Broken Internal Links* / *Broken External Links* — `SeoAuditService::run()['broken_internal_links']` and `SeoAuditService::checkExternalLinks()` respectively, called as-is. External links are a manual "Scan external links" action for the same reason as SEO Center: real HTTP requests, deliberately not run on every page load.
  - *No Inbound Links* / *No Outbound Links* / *Weak Internal Linking* / *Excessive Internal Links* — new capabilities `SeoAuditService` doesn't provide (it only reports binary orphan status for published content). Computed by `App\Services\InternalLinking\LinkGraphService::build()`, which counts inbound/outbound links per node across **all** statuses (including drafts — unlike the orphan check, since the point here is link-planning before publish too). Weak = 1 inbound but below `WEAK_INBOUND_THRESHOLD` (2); Excessive = outbound above `EXCESSIVE_OUTBOUND_THRESHOLD` (100). Both are class constants on `LinkGraphService` — adjust there.
  - *Redirect Chains* — always reports zero findings, on purpose: **this app has no URL redirect mechanism at all** (no redirects table, no redirect middleware). A changed slug produces a "Broken Internal Link" instead of a redirect chain. Building a real redirect system was explicitly out of scope for this feature (it's a separate, larger feature) — the check stays in the UI as an honest zero-state, not a fabricated one, matching the same pattern as SEO Center's "Missing Canonicals".
- **No duplicate link-parsing logic.** `App\Services\Seo\InternalLinkResolver` (extracted from `SeoAuditService` specifically so this feature could reuse it) is the single place that knows how to classify a href as external/skippable and parse an internal path into `{type, locale, slug}` — both `SeoAuditService` and `LinkGraphService` depend on it. `LinkGraphService::build()` also reuses `SeoAuditService::collectContentItems()` (now `public`, with `category`/`slug` added to its return shape — additive, doesn't change SEO Center's behavior) and `SeoAuditService::allLinkSources()` (also now `public`) rather than re-fetching Article/Page/menu/footer link sources a second way. `LinkGraphService::build()` builds one in-memory lookup and walks each item's links once — O(items + links), not one query per link like `InternalLinkResolver::internalPathExists()` (which SEO Center still uses for its own per-link check, and is fine at this content volume).
- **Keyword Mapping**: `keywords` table (polymorphic `keywordable_type`/`keywordable_id` + `keyword` string, morph map registered in `AppServiceProvider` as `'Article' => Article::class, 'Page' => Page::class` so DB values stay short and match the string convention used everywhere else in the SEO/DAM code, e.g. `SeoAuditService`'s `type` field). `Article`/`Page` both get a `keywords(): MorphMany` relation. Editable in `ArticleForm`/`PageForm` via `Repeater::make('keywords')->relationship()` (Filament manages create/update/delete of the related rows automatically — this is the first real `Repeater::relationship()` usage in the app; the existing `faqs` repeater is a plain JSON column, not a relationship, so don't copy that pattern for relation-backed repeaters). Locale is never stored on `Keyword` itself — it's inherited from the parent `Article`/`Page` row, consistent with "two rows per translation" (see Multilingual Architecture); this is also why keyword-based suggestions never cross locales, with no extra locale-filtering code needed.
- **Suggestions are rule-based, not AI/ML** — this remains true of `SuggestionEngine` itself. `App\Services\InternalLinking\SuggestionEngine` scores every same-locale, not-already-linked candidate pair for a target that needs links (inbound < 2) on three explainable signals — keyword overlap (candidate body/title mentions one of the target's keywords, highest weight), category match (`Article.category` string equality — `Page` has no category, so Page-involving pairs never get this signal), and simple word-overlap similarity (Jaccard over a small EN+TR stopword-filtered word set) — summed into a 0–100 confidence score, capped at `MAX_SUGGESTIONS_PER_TARGET` (3) per target and filtered below `MIN_CONFIDENCE` (30). Every suggestion's `reason` field spells out exactly which signals fired, so nothing is a black box. (The AI Content Assistant, Section 23, can *also* write `internal_link_suggestions` rows now — tagged `origin = 'ai'` — but that's a separate code path, not a change to this engine.)
- **Suggestions persist and survive regeneration.** `internal_link_suggestions` (unique on `source_type,source_id,target_type,target_id`) holds `pending`/`approved`/`dismissed` rows. `SuggestionEngine::generateAndPersist()` upserts fresh `pending` suggestions and deletes stale `pending` ones that no longer compute (content changed), but **never touches `approved`/`dismissed` rows** — an admin's decision is permanent history, not something a re-run can silently undo. Generation is dispatched via the queued `App\Jobs\GenerateInternalLinkSuggestions` job ("use queues where appropriate" — scoring is O(n²) over all content) — the page's "Generate suggestions" button explicitly warns that a queue worker must be running (same operational reality already documented in Deployment Workflow for newsletters/AI import), since this project's worker isn't guaranteed to be running on every deploy.
- **Approving a suggestion never rewrites existing content.** It **appends** a real `<a href>` (with the recommended anchor text) to the end of the source `Article`/`Page` body, wrapped in a `<p data-internal-link-suggestion="{id}">` marker so re-approving the same suggestion never inserts a duplicate link. This was a deliberate, conservative choice over inserting mid-content — the RichEditor body is arbitrary HTML, and there's no reliable, safe way to find "the right sentence" to inject a link into without risking corrupting an admin's existing formatting. Bulk approve/dismiss (checkboxes + "Approve selected"/"Dismiss selected") call the same single-suggestion path per row.
- **Link graph visualization** is a deterministic server-side circular layout (`InternalLinkingCenter::getGraphDataProperty()` computes `x`/`y` per node with a fixed radius, no client-side physics) rendered as inline SVG in the Blade view — filterable by language/category/type. No JavaScript graph library was added (canvas/D3/vis-network etc.) — this is an admin-only tool where a simple, robust, dependency-free layout was judged sufficient; revisit only if content volume grows enough that a circular layout becomes unreadable (see Future Development Guidelines).
- **"Categories"/"Tags" don't exist as dedicated models in this codebase** — `Article.category` is a free-text string column (no `categories` table, no `Category` model), and there was never a `Tag`/tagging system at all. This feature reuses `Article.category` as-is (string equality for the category-match signal and the graph's category filter) rather than building a taxonomy system that wasn't asked for; **Keyword Mapping is the new, explicitly-requested feature that fills the "tag-like" semantic-relevance role** for suggestion scoring. If a real `Category`/`Tag` model system is wanted later, that's a separate, explicitly-scoped feature — don't retrofit it into this one.
- Testing follows this project's established pattern (service layer, not the Livewire page): `tests/Feature/InternalLinkingTest.php` covers `LinkGraphService`, `SuggestionEngine`, `InternalLinkResolver`, and the `Keyword` relation directly. The `InternalLinkingCenter` page's own Livewire interactions (tabs, bulk actions, CSV export, graph filters) were verified manually in a browser during development — see Testing Strategy.

## 23. AI Content Assistant

A "professional writing workspace" embedded directly in the Article/Page editor — a collapsible right sidebar on desktop, a bottom drawer on mobile — that calls a **real, live AI provider** to generate/improve specific fields, chat about the current content, run a local content-review audit + health score, translate into a real linked draft, and suggest links/schema/image ALT text, with mandatory preview-before-apply, full generation history, and cancellation. This is the **first real LLM API integration in this codebase** — everything else in "AI Studio" (`ArticleImportService`, `AiImport`, `DraftQueue`, `ImportLog`) is a paste-already-generated-content-in pipeline with zero outbound AI calls (see Architecture and Section 22).

This feature shipped in two rounds: an initial standalone-page version, then a redesign (documented throughout this section) that extracted all its logic into a shared Livewire component and embedded it in the editor, without rebuilding any of the underlying generate/apply/restore/provider machinery — see "Sidebar embedding" below for exactly what moved versus what's unchanged.

- **Sidebar embedding — the component moved, the logic didn't.** All generate/apply/restore/review/chat/translate/cancel logic lives in `App\Livewire\AiAssistantPanel` (this project's first plain Livewire component, `extends \Livewire\Component`, not a `Filament\Pages\Page` — see Section 6). It's mounted twice from the same class and Blade view: embedded in `EditArticle`/`EditPage` (via a custom `$view` override wrapping `{{ $this->content }}` in a two-column layout, collapsible sidebar ⇄ bottom-drawer below `1024px`, Alpine.js state in `localStorage`) and by the thin `App\Filament\Pages\AiContentAssistant` page (kept live as a standalone fallback/deep link, `standalone=true`). The header "AI Assistant" button on the edit page dispatches a `toggle-ai-sidebar` browser event instead of navigating away — the admin never leaves the editor. See Section 6 for the full mechanics.
- **Provider layer is abstract on purpose, per explicit requirement.** `App\Services\AiAssistant\Contracts\AiProvider` (`respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string`) is the low-level contract every vendor class implements. **This originally shipped with a single container binding** (`AiProvider::class` → `NullProvider`/`AnthropicProvider` in `AppServiceProvider::register()`, keyed off `config('services.anthropic.driver')`) that `ContentAssistantService` called directly — **that binding still exists unchanged and is still the ultimate fallback**, but `ContentAssistantService` no longer calls it directly; it now goes through `App\Services\AiAssistant\ProviderManager`, which supports five vendors, per-field routing, and failover — see Section 24 for the full current design. `$options['max_tokens']` is resolved per-field from `ActionRegistry`'s optional `max_tokens` key (`body`/translate need a larger cap than a one-line SEO title) and always forwarded through to the provider's `respond()` — earlier this was accepted but silently dropped; fixed as part of the `body` field work. Legacy credentials: `config/services.php` → `anthropic.key`/`anthropic.model`/`anthropic.driver`, sourced from `ANTHROPIC_API_KEY`/`ANTHROPIC_MODEL`/`AI_ASSISTANT_DRIVER` — `.env.example` lists the key as empty/discoverable, never a real value; this remains fully live as the no-database-configuration fallback (Section 24).
- **Every generatable field is one row in `App\Services\AiAssistant\ActionRegistry`** (label, which model(s) it applies to, which one-click modes are valid for it, expected response shape, and the system-prompt instruction) — the same "add one row to extend" spirit as `ArticleImportService::ALIASES`. Fields: `seo_title`, `meta_description`, `og_title`, `og_description`, `excerpt` (Article-only), `faq` (Article-only), `outline`, `cta`, `tags`, `slug`, `category` (Article-only), `body` (`response_shape: 'html'`, modes `improve`/`rewrite`/`expand`/`shorten`/`simplify` — deliberately **no** `generate` mode, since the assistant lacks enough context to write a whole article from nothing; this is what makes "improve the introduction"/"rewrite the conclusion"/"make it shorter" buildable at all — see AI Chat below), `content_review_summary`, `internal_links`, `external_links`, `schema`, `alt_text`, `caption`, `translate` (modes are target locales `en`/`tr`, not edit modes — see Translate below). A field's `appliable` key (default `true`) marks whether "Apply" writes anywhere at all — `content_review_summary`/`internal_links`/`external_links`/`schema`/`caption`/`translate` are `appliable: false`, and are rendered outside the main per-field grid (`AiAssistantPanel::getSuggestionFieldsProperty()`), not inside it (`getFieldsProperty()` filters them out).
- **`seo_title`/`meta_description`/`og_title`/`og_description` are real, new nullable columns** on both `Article` and `Page` (migrations `2026_07_16_00001{2,3}_add_seo_fields_to_*_table.php`) — before this feature, no such columns existed anywhere. **The public templates prefer these columns when set, falling back to the exact previous behavior when blank** (`$record->seo_title ?: $record->title`, etc., in `blog-post.blade.php`/`page.blade.php` and their `tr/` counterparts) — a blank field is indistinguishable from "this feature doesn't exist." `ArticleImportService::import()` does **not** write these columns (a pre-existing, documented gap — `seo.title` is explicitly `mapping['skipped']` there, "page titles are always built from the article title on this site") — this matters for Translate below, since a translated draft's SEO fields start blank just like any hand-created article, not silently populated.
- **Queued, with visible progress.** `App\Models\AiGeneration` (`status`: `queued`/`processing`/`completed`/`failed`/`cancelled`) backs every async operation — field generation, chat-triggered generation, and translation all create one row. `AiAssistantPanel`'s Blade view adds `wire:poll.3s="$refresh"` while `isPolling` (any generation queued/processing) or `isChatPending` (last chat message is from the user, awaiting a reply) is true. `getGenerationProgressProperty()` shows "X of Y done" during a batch (Optimize Entire Article, Quick Actions) by comparing pending vs. total generations created for this record in the last 5 minutes — no `batch_id` column, just a time-windowed count. `App\Jobs\RunAiContentGeneration` (`$tries = 1` — an AI API call is not safe to silently retry) does the actual field generation.
- **`AiGeneration` is the generation history/restore table** — modeled on `ImportLog`'s shape (status enum, provider column, `content_type`/`content_id` using the same short-string morph map as `keywords`/`internal_link_suggestions`) rather than `spatie/laravel-activitylog` (wrong shape for large generated payloads with restore semantics). `input_snapshot` captures the field's value **immediately before** each run so **Restore** can write it back later; `canRestore()` checks `applied_at !== null && restored_at === null` — not whether `input_snapshot` is non-null, since restoring *to* a legitimately-empty value must still work. `scopeForField()` (one field) and `scopeForRecord()` (every field — backs the History tab, see below) are the two query entry points; `isCancellable()` is `status in [queued, processing]`.
- **Nothing is ever written automatically — this is the core, explicit requirement of the whole feature.** `ContentAssistantService::generate()` only returns `{result, warnings}`; it never touches the record, and neither does `buildTranslationPayload()`/`classifyIntent()`. Writing happens in a small, fixed set of places, each an explicit admin click with a `wire:confirm` guard: `AiAssistantPanel::applyGeneration()` (writes `result` into the field, stamps `applied_at`/`applied_by`), `::restoreGeneration()` (writes `input_snapshot` back), `::applyInternalLinkSuggestions()`, and the queued jobs that create real records (`TranslateArticleDraft`) — all go through Eloquent, so `Article`'s `LogsActivity` hook still fires (Section 20's "don't bypass Eloquent for content changes" rule applies here too).
- **`alt_text` is the one field whose Apply/Restore target is a *different* model.** ALT text lives on the DAM's `Media.alt_text`, keyed by matching `Media.disk_path` to the record's `image_path` — via `Media::forRecord(Model $record): ?Media`, a static helper on the `Media` model itself (extracted here specifically because both `AiAssistantPanel` and `App\Jobs\ProcessAiChatMessage` need the same three-line lookup — the second real caller is what justified pulling it out of `AiAssistantPanel::mediaForRecord()`, which now just delegates to it). `generateField()`/`applyGeneration()`/`restoreGeneration()` special-case `field === 'alt_text'` to read/write `Media`, not the Article/Page record. `caption` has no storage anywhere (not on `Article`/`Page`, not on `Media`) and is intentionally `appliable: false`.
- **`internal_links` reuses `internal_link_suggestions`/`SuggestionEngine`'s exact lifecycle, unchanged.** `AiAssistantPanel::applyInternalLinkSuggestions()` writes `pending` rows into the same table, tagged `origin = 'ai'`. Approval still goes through `InternalLinkingCenter`'s existing, CLAUDE.md-§27-protected `insertLinkForSuggestion()` — completely untouched by this feature.
- **`external_links` and `schema` are suggestion-only, never auto-applied** — no infrastructure exists to safely verify external content, and template-level schema wiring needs explicit sign-off. Every AI-suggested external URL is checked with `SeoAuditService::checkUrls()` before being shown.
- **Diff preview — a self-built, zero-dependency word-level diff.** `App\Services\AiAssistant\DiffService::diffWords()` is a classic LCS (longest-common-subsequence) algorithm over whitespace-preserving tokens — no diff library exists anywhere in this codebase (`sebastian/diff` is a `packages-dev`-only transitive `phpunit` dependency, excluded from `composer install --no-dev`), so this was built rather than adding a dependency for something this size doesn't need (Section 13). `AiAssistantPanel::diffFor()` only runs it for scalar `text`/`html` fields (SEO title, meta description, excerpt, cta, slug, category, body) — array-shaped results (FAQ, tags, outline, link suggestions) keep their existing list rendering, since a word diff isn't meaningful there. For the `body` field specifically, both sides are HTML — `diffWords()` strips tags before comparing, so the diff is a visual approximation (word-level, not byte-perfect HTML), an accepted simplification given no HTML-diff library exists either. Rendered as `<del>`/`<ins>` (red/green) in place of the plain-text preview, only before an unapplied "Apply" — once applied, the diff is gone (nothing to compare against).
- **AI Health Report — a score card computed from existing, unmodified services, not new audit logic.** `ContentReviewService::scoreCard(Model $record)` returns six categories (SEO, Readability, Content Quality, Internal Linking, Media Optimization, Schema) each `{score: 0-100, issues: string[]}`, plus a simple, unweighted `overall` average — explainable, no hidden weighting (same ethos as `SuggestionEngine`'s `reason` field). Every category reuses something that already existed: SEO checks `seo_title`/`meta_description`/`og_title`/`og_description` presence+length (the same fields `SeoAuditService` tracks); Readability/Content Quality reuse `ContentReviewService::review()`'s own findings (missing headings, long paragraphs, missing-FAQ-opportunity, weak-CTA); Internal Linking calls `LinkGraphService::build()` and reads this record's node (inbound/outbound counts); Media Optimization uses `Media::forRecord()` + `Media::warnings()`; Schema hard-codes the same Article-always/Page-never rule `SeoAuditService::missingSchema()` already documents (Article scores 100, Page scores 40 with an explanatory issue — a template-level gap, not fixable per-record). Rendered as a card fixed at the top of the sidebar.
- **`body` field — the first field that can touch article content, not just metadata.** Modes `improve`/`rewrite`/`expand`/`shorten`/`simplify` (see ActionRegistry bullet above for why there's no `generate`). `ContentAssistantService` shows the AI the **raw HTML** body (not the `strip_tags()`'d version every other field's prompt uses) so it can see and preserve heading/list structure, under a higher `MAX_BODY_HTML_CHARS` cap (12,000 vs. 6,000) than the plain-text context other fields get; the "Current value of…" line is skipped for `body` since it would just repeat the same content already shown. The response is cleaned (`trim` + strip markdown code fences) but never HTML-escaped or re-parsed — stored exactly like a normal `body` value.
- **Quick Actions + "✨ Optimize Entire Article" — thin wrappers over the existing generate pipeline, no new generation logic.** `AiAssistantPanel::queueGeneration()` (private) is the one place that creates an `AiGeneration` + dispatches `RunAiContentGeneration`; `generateField()` (single field), `quickSeoOnly()` (seo_title/meta_description/og_title/og_description/slug), `quickFaqOnly()` (`faq`), `quickBodyAction($mode)` (`body`), and `optimizeEntireArticle()` (every field with a `generate` mode, except `content_review_summary` and `body`, which has none) all call it and share one `notifyQueued()` notification helper. "Generate Everything" (Quick Actions) and "✨ Optimize Entire Article" (the primary button) are **the same action** — the spec asked for it under two headings, so it's one method (`optimizeEntireArticle()`) with two buttons. Nothing here auto-applies; it only queues suggestions.
- **AI Chat — a thin NLU layer routing into the existing pipeline, not a new generation mechanism.** `ai_chat_messages` table + `App\Models\AiChatMessage` (`role`: `user`|`assistant`, optional `related_generation_id`) store the thread per record, rendered at the **top of the sidebar** (`AiAssistantPanel::sendChatMessage()` → queued `App\Jobs\ProcessAiChatMessage`). `ContentAssistantService::classifyIntent(Model $record, string $message)` calls the same `ProviderManager::respond()` every field uses (Section 24), with a system prompt built from `ActionRegistry::applicableTo()`'s field/mode list (not hardcoded), and returns `{intent: 'action'|'translate'|'chat', field, mode, target_locale, reply}` — validated server-side (`ActionRegistry::exists()` + mode/model checks) before being trusted, falling back to `chat` if the AI's field/mode/locale choice doesn't check out. `ProcessAiChatMessage` then either creates a normal `AiGeneration` + dispatches the existing `RunAiContentGeneration` (`action`), creates an `AiGeneration` + dispatches `TranslateArticleDraft` (`translate`), or just stores the assistant's `reply` (`chat`) — in all three cases, no generation logic is duplicated, only routed to.
- **Translate — a real linked draft, not a text suggestion (explicit requirement, "Full" scope confirmed with the user).** `ContentAssistantService::buildTranslationPayload(Model $record, string $targetLocale)` asks the AI to translate **only content fields** (title, body, plus excerpt/faqs for Article) — it never decides locale, status, or `translation_of`; that metadata is always assembled in code (`App\Jobs\TranslateArticleDraft`), so the AI is never responsible for a publish-affecting decision. For Article, the job builds an import-shaped payload and calls the existing, unmodified `ArticleImportService::import()` — reusing validation/slug-uniqueness/`ImportLog`, with `status` forced to `draft` (same policy as the AI Import API's `forceDraft()`). **Caught during testing:** passing `faqs: null` through to `import()` failed validation, because `ArticleImportService` checks `array_key_exists('faqs', ...)`, not truthiness — fixed by `array_filter()`-ing empty/null keys out of the payload entirely rather than passing them as null. Page has no import service (its shape — `locale`/`title`/`slug`/`body`/`image_path`/`status` — is much simpler), so it's created directly via Eloquent with the same slug-uniqueness guarantee (`Page::locale()->where('slug', ...)`, suffix-and-retry on collision). Progress is a normal `AiGeneration` row (`field: 'translate'`, `mode`: target locale) — the existing queued/progress/cancellation UI covers it with zero new plumbing. On completion, the sidebar's Translate card links straight to the new draft's edit page.
- **Cancellation — the honest ceiling of what's possible on the `database` queue driver.** `AiAssistantPanel::cancelGeneration()` flips a `queued`/`processing` generation to `status = 'cancelled'`. `RunAiContentGeneration` and `TranslateArticleDraft` each check twice: before starting (skip entirely if already cancelled — catches a cancel that lands between dispatch and worker pickup) and right before writing the final result (a `fresh()` re-read from the database, not the in-memory object, so a cancel that happened *during* the API call is still honored). This cannot kill an in-flight HTTP call — "cancel" means "the result will be discarded," not "stop immediately," and the code/comments say so rather than overpromising. `TranslateArticleDraft`'s post-API-call checkpoint runs **before** the new Article/Page row is created, so a late cancel never leaves an orphaned draft behind.
- **History tab — the same apply/restore/cancel actions, just listing every field, not one.** `AiGeneration::scopeForRecord()` (parallel to `scopeForField()`) backs a third sidebar tab (`generate`/`review`/`history`) showing the latest 30 generations across every field for the record — each row picks whichever action applies (Cancel while pending, Restore if already applied, Apply/"Add suggestions" otherwise) via the exact same methods the per-field cards already call.
- **Content Review (`App\Services\AiAssistant\ContentReviewService`) is deterministic, not AI** — same "fast, always-on, free" posture as `SeoAuditService`. It extends `HtmlContentScanner` with `headings()`/`paragraphs()` and reuses `InternalLinkResolver` for link classification. Checks: missing/misordered headings, long paragraphs (Unicode-safe word count so Turkish diacritics count correctly), missing internal/external links, missing ALT text, duplicate keywords, a missing-FAQ-opportunity heuristic (Article-only), and a weak-CTA heuristic (EN+TR phrase list). The one optional AI touch is `content_review_summary`, which reuses the exact same generate/job/history pipeline to narrate the findings in plain language. `scoreCard()` (see AI Health Report above) is new scoring math over these same checks, not a new audit pass.
- Testing follows this project's established pattern (service/job layer, not the Livewire component's own rendering — though its plain public methods are called directly, since they contain real business logic): the original `tests/Feature/AiContentAssistantTest.php` still covers `ActionRegistry` scoping, both providers, `ContentAssistantService`'s prompt-building/response-parsing, `RunAiContentGeneration`'s status transitions, apply/restore round-trips (including `alt_text` → `Media`), `ContentReviewService`'s checks, and `SeoAuditService::checkUrls()` — unchanged by the redesign. The new `tests/Feature/AiAssistantPanelTest.php` covers everything added in the redesign: `DiffService`, `scoreCard()`, the `body` field + Quick Actions/Optimize Entire Article, `classifyIntent()` + `ProcessAiChatMessage` (including the translate-intent routing), `buildTranslationPayload()` + `TranslateArticleDraft` (Article and Page paths, including the `faqs: null` validation fix), cancellation (both jobs' checkpoints), and `scopeForRecord()`/the History tab. The sidebar's own Livewire interactions (tabs, drawer collapse, chat polling) were verified manually in a browser during development — see Testing Strategy.

## 24. AI Provider Integration Layer

Added after the AI Content Assistant (Section 23) shipped with a single, container-bound `AnthropicProvider` — this section covers the production-ready **multi-provider layer** that sits between every AI feature and the actual vendor call. It is purely additive: nothing in Section 23's feature set (generate/apply/restore, chat, translate, cancellation, health score, history) was rebuilt — `ContentAssistantService` still does exactly what it did before, it just calls `ProviderManager::respond()` instead of an `AiProvider` bound once in the container.

- **Two layers, not one.** `App\Services\AiAssistant\Contracts\AiProvider` (`respond(string $systemPrompt, string $userPrompt, array $images = [], array $options = []): string`) is unchanged — it is still the low-level, per-vendor contract every provider class implements. `App\Services\AiAssistant\ProviderManager` is the new layer above it: `ContentAssistantService`'s three call sites (`generate()`, `classifyIntent()`, `buildTranslationPayload()`'s caller) now inject `ProviderManager`, never an `AiProvider` directly, and pass an `actionKey` (the `ActionRegistry` field key, e.g. `seo_title`, `translate`) on every call. Nothing else in the codebase is coupled to a specific vendor — the Anthropic-specific request/response shape lives only inside `AnthropicProvider`.
- **Five vendor implementations.** `AnthropicProvider` (rewritten to accept an optional `App\Services\AiAssistant\Support\ProviderCredentials` DTO — falls back to `config('services.anthropic.*')` when none is given, which is what makes the legacy path below work unchanged), `OpenAiProvider`, `GeminiProvider`, `GrokProvider`, `DeepSeekProvider`, plus `NullProvider` as the always-available "not configured" fallback. OpenAI, xAI Grok, and DeepSeek all speak the same OpenAI-compatible Chat Completions shape, so they share one abstract base, `OpenAiCompatibleProvider` — each subclass is a few lines (base URL + auth header). Gemini has a genuinely different request shape (`generateContent`, API key in the query string, `system_instruction`/`contents`/`generationConfig`, images sent as base64 `inline_data` rather than public URLs — fetched and encoded server-side, with a single bad image skipped rather than failing the whole request) so it's its own standalone class. **Adding a 6th vendor is: one new class implementing `AiProvider`, one entry in `ProviderManager::DRIVERS`, and one seeded `ai_provider_configs` row (migration or `tinker`) — no other file changes.**
- **Usage/token reporting is opt-in via a second, separate interface.** `App\Services\AiAssistant\Contracts\UsageAwareProvider` (`lastUsage(): ?array`, returning `{prompt_tokens, completion_tokens}`) is implemented by all five real providers but was kept as its own interface — checked via `instanceof` in `ProviderManager` — specifically so it never has to be part of the core `AiProvider` contract that everything else depends on.
- **Database schema — five new tables, all under the `ai_` prefix used throughout AI Studio**: `ai_provider_configs` (one row per vendor — `slug` is the fixed key `ProviderManager::DRIVERS` looks up, `api_key` is `encrypted`-cast, `is_enabled`/`base_url`/`default_model`/`max_tokens`/`temperature`/`timeout_seconds`, plus `last_tested_*` columns written by Test Connection), `ai_provider_models` (an admin-maintained model catalog per provider — label + model ID + optional per-million-token input/output pricing, used only for cost estimation, never a hardcoded list), `ai_action_overrides` (unique on `action_key` — a row means "this field always uses this provider/model"; no row means "use the default provider," which is the deliberate empty-state semantics throughout this feature, not a bug to "fix" by seeding one row per field), `ai_usage_logs` (one row per `ProviderManager::respond()` call, success or failure — **denormalized** `provider_slug`/`model` as plain strings, not foreign keys, so deleting/renaming a provider config never breaks historical usage data — and it **never** stores the API key, prompt, or response text, only token counts/cost/timing/a sanitized error message, per the explicit "never log API keys" requirement), `ai_provider_settings` (a singleton row — always `id=1` via `AiProviderSetting::current()` — holding the global default provider, the failover on/off flag, and the fallback provider). A seed migration pre-creates the five `ai_provider_configs` rows (Anthropic/OpenAI/Gemini/Grok/DeepSeek), all disabled with no key, so the Provider Settings screens show real rows on first load rather than an empty "create new" form — see "Filament UI" below for why this also means there is no Create/Delete UI for providers.
- **Resolution order, and the legacy `.env` fallback is load-bearing, not cosmetic.** `ProviderManager::resolveCandidates(?string $actionKey)`: (1) if an `ai_action_overrides` row exists for this `actionKey` **and** its provider is `is_usable` (enabled AND has a key), use it; (2) else if the global default provider (`ai_provider_settings.default_provider_config_id`) is usable, use it; (3) else — nothing configured in the database at all — fall back to the **exact same** `AnthropicProvider` instance bound in `AppServiceProvider` from `config('services.anthropic.*')`/`ANTHROPIC_API_KEY`, unchanged since before this feature existed. This third path is what the user explicitly confirmed as a requirement ("Keep .env as automatic fallback") — it means every existing installation that only ever set `ANTHROPIC_API_KEY` in `.env` keeps working identically after this migration, with zero action required. If failover is enabled and the primary candidate came from the database, the configured fallback provider (if itself usable and different from the primary) is appended as a second candidate.
- **Per-field override, not per-category — the confirmed UX decision.** `ai_action_overrides` is keyed by the individual `ActionRegistry` field (`seo_title`, `meta_description`, `translate`, ...), not by a coarser group. The `AiActionRouting` admin page (below) groups these fields under collapsible section headers (SEO/Content/Translation/Media/Links/Schema) purely for visual organization — every field still gets its own independent Select, defaulting to "Use Default Provider" (i.e., no row in the table), exactly as confirmed with the user rather than a coarser per-category override that would have been simpler to build but less flexible.
- **Retry, failover, and usage logging all happen inside `ProviderManager::attempt()`/`respond()`, transparently to every caller.** Each candidate provider gets up to 2 attempts (1 retry after a 300ms backoff, for transient errors) before being marked failed and falling through to the next candidate. Every attempt — success or final failure — writes exactly one `ai_usage_logs` row. **Usage logging can never turn a successful AI call into a reported failure**: `logUsage()` wraps its own body in `try/catch (Throwable $e) { report($e); }` — a database error while writing the log row is swallowed and reported to the application log, never allowed to bubble up and discard an already-successful generated result (this was a real defect caught during this feature's own development, not a hypothetical). If every candidate fails, `respond()` throws a `RuntimeException` wrapping the last real exception — callers (the existing `RunAiContentGeneration`/`ProcessAiChatMessage`/`TranslateArticleDraft` jobs) already catch and report failures exactly as they did before this feature, unchanged.
- **Cost estimation is opt-in, per model, and never guesses.** `ProviderManager::estimateCost()` looks up the calling provider+model combination in the `ai_provider_models` catalog; if no matching row has both `input_price_per_million` and `output_price_per_million` set, `estimated_cost_usd` is stored as `null` — never a fabricated number. This mirrors the same "null over a guess" posture as `SuggestionEngine`'s confidence scoring and `Media::warnings()` elsewhere in the codebase.
- **Security — API keys are encrypted at rest and redacted from every error path.** `AiProviderConfig::api_key` uses Eloquent's `encrypted` cast — this project's **first use of encryption anywhere** in the codebase. The Filament edit form (`AiProviderConfigForm`) never re-displays a decrypted key: the field is blanked on every load (`afterStateHydrated`) and only written back if the admin actually types a new value (`dehydrated(fn ($state) => filled($state))`) — leaving it blank keeps the existing key untouched, the same "shown once, then hidden" posture as `ApiTokenResource` uses for AI Import API tokens, adapted for a field that (unlike a one-way hash) must remain usable by the app. `ProviderManager::sanitizeError()` regex-redacts `key=`, `Bearer `, and `x-api-key:` patterns out of any exception message **before** it is persisted to `ai_usage_logs.error_message` — defends against a misbehaving vendor response echoing the key back, not just against the app's own logging.
- **`ProviderManager::testConnection(AiProviderConfig $config)`** builds a provider directly from one config row — bypassing `resolveCandidates()` entirely, so a provider can be tested before it's enabled or set as anyone's default — sends a minimal `"Reply with exactly one word: OK"` call, measures latency, and persists `last_tested_at`/`last_test_status`/`last_test_latency_ms`/`last_test_model`/`last_test_error` onto that same row. Deliberately does **not** write to `ai_usage_logs` — a connection test is not a real content-generation request.
- **Filament UI — two new screens, both under the "AI Studio" nav group (Section 6):**
  - **`App\Filament\Resources\AiProviderConfigs\AiProviderConfigResource`** (nav "AI Providers") — **List and Edit only, deliberately no Create or Delete.** The five rows are seeded by migration and each `slug` is a fixed key `ProviderManager::DRIVERS` looks up; a Create form would let an admin type an arbitrary slug that doesn't match any real provider class (`buildProvider()` would throw at generation time), and Delete would orphan any `ai_action_overrides`/`ai_provider_settings` row pointing at that config. The Edit form covers every field the spec asked for (name, masked API key, base URL, default model, max tokens, temperature, timeout, enabled toggle) plus a `Repeater::make('models')->relationship()` model catalog (same syntax precedent as `ArticleForm`'s `keywords` repeater — Section 6). The table's row actions are **Test Connection** (calls `ProviderManager::testConnection()`, shows a success/failure notification with latency) and **Set as Default** (writes `ai_provider_settings.default_provider_config_id`).
  - **`App\Filament\Pages\AiActionRouting`** (nav "AI Routing") — a `HomepageSettings`-style settings Page (not a Resource): a "Global defaults" section (default provider, failover toggle, fallback provider) plus one collapsible section per category (SEO/Content/Translation/Media/Links/Schema) containing every `ActionRegistry` field's own provider Select (placeholder "Use Default Provider") and an optional model-override text input, shown only once a provider is chosen for that field. `save()` upserts/deletes `ai_action_overrides` rows to match the form state — a field reset to "Use Default Provider" **deletes** its override row rather than storing an explicit null, keeping the "no row = default" convention clean.
  - **`App\Filament\Resources\AiUsageLogs\AiUsageLogResource`** (nav "AI Usage Logs") — fully read-only (`canCreate()`/`canEdit()`/`canDelete()` all `false`), following `ImportLogResource`'s established shape exactly: filters (provider/status/action/user/date range), a per-row "Error" modal action (visible only on failed rows) showing the sanitized `error_message`, and an "Export CSV" bulk action over the selected rows (same `response()->streamDownload()` pattern as `NewsletterSubscribersTable`).
- **Testing**: `tests/Feature/ProviderManagerTest.php` covers `ProviderManager` directly against `Http::fake()` — the legacy `.env` fallback (both "nothing configured anywhere" and "DB configured but unusable"), the DB-configured default provider taking priority, per-action override (including one pointing at an unusable provider falling back to the default), failover on and off, cost estimation with and without catalog pricing, and API-key redaction in a persisted error message. `tests/Feature/AiProviderSettingsTest.php` covers the `AiProviderConfigResource`/`AiActionRouting` Filament screens: encrypted key save/redisplay behavior, Test Connection success/failure updating the config row, Set as Default, and the routing page's save/delete-on-reset behavior. `tests/Feature/AiUsageLogsTest.php` covers the read-only resource: listing, the read-only guards, the error-detail action's visibility, CSV export, and filtering.

## 25. Editorial Workflow & Content Planner

A production pipeline layer sitting on top of everything else in this file — from a bare idea with
no `Article`/`Page` yet, through drafting, review, scheduling, and publication — built as a genuinely
new layer, not a rebuild of anything: scheduling/publishing, AI generation, SEO scoring, tagging
infrastructure, and the activity log are all reused exactly as they existed before this feature.

- **`Tag` is deliberately separate from `Keyword` — confirmed, not an oversight.** `App\Models\Tag`
  (`name`, `slug` auto-generated from `name` on create if left blank, optional `color`) plus a
  polymorphic many-to-many `taggables` pivot (`tag_id`/`taggable_type`/`taggable_id`, unique on all
  three so the same tag can still apply to an `Article` and a `Page` independently) is this project's
  **first real tagging system** — `Keyword` remains untouched and SEO-only (target keywords for
  Internal Linking Center scoring). `Article`/`Page` both get a `tags(): MorphToMany` alongside their
  existing `keywords(): MorphMany`; `ArticleForm`/`PageForm` gained a `tags` multi-select
  (`->relationship('tags', 'name')->multiple()->preload()->createOptionForm([...])`) next to the
  existing `keywords` repeater — two visibly different UI patterns for two deliberately different
  concepts. `App\Filament\Resources\Tags\TagResource` (nav "Tags", the new "Content Planner" group)
  is plain List/Create/Edit/Delete.
- **`App\Models\WorkflowStage`** is the configurable pipeline (`App\Filament\Resources\WorkflowStages\WorkflowStageResource`,
  nav "Workflow Stages"): `label`, unique `slug`, `sort_order` (drag-reorderable in the table via
  `->reorderable('sort_order')`), `color`, `is_default` (exactly one row — `CreateWorkflowStage`/`EditWorkflowStage`
  unset any other row's flag when one is saved with it on), `is_terminal` (informational only), and a
  `checklist_items` JSON array of `{key,label}` pairs — the per-stage checklist template. A seed
  migration creates the eight requested default stages in order (`idea → research → ai_draft →
  human_review → seo_review → scheduled → published → archived`), with SEO Review pre-populated with
  exactly the requested checklist (Meta Title, Meta Description, FAQ, Internal Links, Images, Schema,
  ALT Text). **Only the seven `WorkflowStage::STAGE_*` constant slugs are wired to automatic behavior**
  (materializing a draft, syncing with the scheduler, firing notifications, dashboard math) — admins
  can freely add custom stages or rename/reorder anything else; renaming or deleting one of the seven
  known slugs simply means that specific integration point stops firing for it, not that anything
  breaks. A stage with any `ContentPlan` rows in it cannot be deleted (`restrictOnDelete()` at the DB
  level, and the Delete action is hidden in the UI whenever `contentPlans()->exists()`).
- **`App\Models\ContentPlan`** is the planner card — and the central, explicitly-confirmed design
  decision of this feature: **it can exist as a pure idea with no `Article`/`Page` row at all**
  (`contentable_type`/`contentable_id` nullable polymorphic, `content_type` — `'Article'`/`'Page'` —
  optionally chosen up front or left blank to decide later). Also carries `category` (free string,
  matching `Article.category`'s existing convention — no taxonomy table added here either),
  `workflow_stage_id`, `priority` (`low`/`medium`/`high`/`critical` via `PRIORITY_*` constants),
  `author_id`/`assigned_to` (nullable FKs to `users` — schema-ready for multiple team members; see
  the "future multi-user" note under Important Project Decisions below), `planned_publish_at` (used
  **only** until a real `Article`/`Page` exists), `due_at` (+ `deadline_notified_at` guard — see
  Notifications below), `checklist_state` (JSON, keyed `{stage_slug: {item_key: bool}}` so two
  stages can reuse the same item key without colliding), `notes`, a `tags()` morphToMany (registered
  in the morph map as `'ContentPlan'`), a `tasks()` hasMany, and `LogsActivity` configured the same
  way as `Article` (`logOnly(['title','workflow_stage_id','priority','assigned_to','planned_publish_at','due_at'])`,
  `logOnlyDirty()`, `useLogName('content_plan')`).
  - **`ContentPlan::moveToStage(WorkflowStage $stage, ?User $actor = null)`** is the single write path
    for stage changes (used by the Kanban drag-drop, bulk actions, the calendar's automatic Published
    sync, and the resource form). It's a no-op if already in that stage; otherwise it updates the
    column, records a `ContentPlanStageTransition` row (`from_stage_id`/`to_stage_id`/`changed_by`,
    immutable, no `updated_at`), and — only when entering the `ai_draft` stage with no `contentable_id`
    yet — calls `materializeContent()`. Notifications (below) fire after the DB transaction commits.
  - **`ContentPlan::materializeContent()`** creates the real `Article` (or `Page`) from the idea: empty
    `body`, `status = 'draft'`, title/locale/category copied over, tags synced onto the new record,
    slug de-duplicated per-locale (`title-2`, `title-3`, …). It **never generates content** — the body
    starts empty specifically so the existing AI Assistant (or the admin directly) fills it in inside
    the normal editor, never automatically (see the AI Studio integration bullet below). Idempotent —
    calling it again once `contentable_id` is set just returns the existing record.
  - **`Article`/`Page` both gained a `contentPlan(): MorphOne`** (the reverse of `ContentPlan`'s
    `contentable()`) so other code can go from a materialized record back to its plan — used by
    `PublishDueArticles`, which now also advances a linked plan to the `published` stage (and fires
    `PublishingCompleted`) the moment it auto-publishes a scheduled article; this is the **one**
    small, deliberate addition to that command, not a parallel poller — the cron stays the single
    source of truth for "did this actually publish," the plan just follows along.
- **`App\Models\ContentTask`** — per-plan tasks (`title`, `status` via `STATUS_PENDING/IN_PROGRESS/DONE`
  constants, `due_at`, `assigned_to`, `notes`, `sort_order`), managed as a `Repeater::make('tasks')->relationship()->orderColumn('sort_order')`
  on `ContentPlanResource`'s form — exactly the requested "Write introduction / Create featured image /
  Review SEO / Translate / Approve" shape, with no fixed list of task titles (free text).
- **`App\Models\ContentPlanStageTransition`** exists **specifically for fast dashboard math**
  (average time-to-publish, average time-in-review, production-per-month) — deliberately separate
  from `activity_log` (which `ContentPlan` also writes to, for a human-readable audit trail), the same
  "two mechanisms, two purposes" split already established between `ai_usage_logs` and `activity_log`
  in Section 24. Querying "how long did this spend in review" from Spatie's JSON `properties` column
  would be awkward; a plain ordered table of `(content_plan_id, from_stage_id, to_stage_id, changed_by,
  created_at)` rows is not.
- **Notifications — channel-agnostic by explicit request, in-app only for now.** A standard
  `notifications` table (hand-written migration matching Laravel's `notifications:table` stub — this
  project had never published it before) plus `App\Models\NotificationPreference` (`user_id`,
  `event_key`, `channel`, `enabled`, unique on all three; **opt-out semantics** — no row means enabled,
  so nothing needs seeding for existing installs to keep receiving notifications). Four real
  `Illuminate\Notifications\Notification` classes in `App\Notifications`
  (`WorkflowStageChanged`, `ReviewRequested`, `PublishingCompleted`, `DeadlineApproaching`) — each
  `via()` calls `NotificationPreference::filterChannels($user, self::EVENT_KEY, self::AVAILABLE_CHANNELS)`
  (currently `['database']` only) so adding `'mail'`/`'slack'`/`'telegram'` later is purely additive:
  a new `toMail()`/`toSlack()` method on the class plus extending its `AVAILABLE_CHANNELS` constant,
  no change to any call site. Every class's `toDatabase()` returns
  `Filament\Notifications\Notification::make()->title(...)->body(...)->getDatabaseMessage()` — the
  documented bridge that makes a plain Laravel notification render correctly in Filament's own
  database-notifications bell (`AdminPanelProvider->databaseNotifications()->databaseNotificationsPolling('30s')`,
  this project's first use of that panel feature, **guarded behind `Schema::hasTable('notifications')`** —
  the bell renders on every panel page including `SystemMaintenance`, so on a fresh deploy where code
  landed before this feature's migrations ran (see Deployment Workflow — no SSH, migrations are a
  separate manual step), an unguarded call would 500 the entire admin panel, including the one page
  that can run the pending migration; this was a real regression caught right after this feature
  first shipped, see `tests/Feature/AdminPanelResilienceTest.php`) — a raw custom array would not. `ContentPlan::moveToStage()`
  notifies the assignee (falling back to the author, or nobody if neither is set) on every stage
  change, plus `ReviewRequested` when entering `human_review`/`seo_review` and `PublishingCompleted`
  when entering `published`. `App\Console\Commands\NotifyApproachingDeadlines` (`content-plans:notify-deadlines`,
  hourly via `routes/console.php`, mirroring `articles:publish-due`'s cron pattern) warns about
  `due_at` within 24 hours, excluding plans already in `published`/`archived`; `deadline_notified_at`
  (reset to `null` automatically whenever `due_at` changes, via a `static::saving()` hook on
  `ContentPlan`) stops it from re-notifying every hour for the same deadline.
- **`App\Filament\Resources\ContentPlans\ContentPlanResource`** supplies the actual Create/Edit form
  (title, type, locale, category, priority, author/assignee, tags, planned publish date, draft
  deadline, the tasks repeater, notes, and a `Section` of checklist `Checkbox`es that only renders
  once the record is in a stage whose `checklist_items` is non-empty, bound to
  `checklist_state.{stage_slug}.{item_key}`) plus a shared `ContentPlanTable` class (columns:
  title/locale/category/tags/author/assignee/stage/priority/SEO+AI+readability scores/publish
  date/last updated — the three score columns call `ContentReviewService::scoreCard()` on the linked
  `contentable`, reusing Section 23's existing scoring, never recomputing anything). The resource is
  `shouldRegisterNavigation() => false` — `App\Filament\Pages\ContentPlanner` (below) is the intended
  entry point; this resource only supplies routable Create/Edit pages and the reusable table class.
- **`App\Filament\Pages\ContentPlanner`** (nav "Planner", the "Content Planner" nav group) is one page
  with four switchable views (`$activeView`, `#[Url(as: 'view')]`-bound so it's deep-linkable),
  following the exact same "one Page, several tabs" precedent as SEO Center/Internal Linking Center/AI
  Content Assistant rather than four separate nav items:
  - **Kanban** — one column per `WorkflowStage` (by `sort_order`), cards showing everything the spec
    asked for (title/language/category/tags/author/stage/priority/the three reused scores/publish
    date/last updated). Drag-and-drop uses the exact same vanilla HTML5 Drag-and-Drop pattern as
    `EditorialCalendar` (native `dragstart`/`dragover`/`drop`, `@this.call(...)`, re-wired via
    `Livewire.hook('morph.updated', ...)`) — no Kanban library was added, matching this project's
    "build small custom JS instead of a new dependency" precedent. Checkbox multi-select
    (`wire:model.live="selectedPlanIds"`, a plain array-bound checkbox group) backs a bulk-action bar
    (move stage / set priority / delete).
  - **Calendar** — **relocates**, not rebuilds, `EditorialCalendar`'s month/week grid computation and
    reschedule logic, extended to show `Page`s alongside `Article`s and two new pin types sourced from
    `ContentPlan`: "planned" (an idea's `planned_publish_at`, only while `contentable_id` is still
    null) and "deadline" (`due_at`, hidden once the plan reaches `published`/`archived`). Dragging any
    chip still only changes the date, preserving time-of-day — `rescheduleItem($kind, $type, $id,
    $newDate)` dispatches to the right column (`published_at` on the `Article`/`Page`,
    `planned_publish_at`, or `due_at`) depending on the chip's `data-kind`. `EditorialCalendar` itself
    is now a **thin redirect** (`mount()` → `redirect(ContentPlanner::getUrl().'?view=calendar')`,
    `shouldRegisterNavigation() => false`) — the old URL keeps working, but there is only one calendar
    now, not two; its old Blade view was deleted as dead code.
  - **Table** — a hand-rolled Blade table (matching the SEO Center/Internal Linking Center convention
    rather than mixing in Filament's separate `HasTable` component on a page that already has three
    other hand-rolled views) reusing the **same** `filteredPlans` collection as Kanban — no second
    query.
  - **Dashboard** — stat cards (Ideas/Drafts/In Review/Scheduled/Published, grouped straight off
    `content_plans.workflow_stage_id`) plus average days-to-publish (creation → first `published`
    transition per plan) and average days-in-review (only counting review-stage visits that have
    since moved on — a plan still sitting in review isn't averaged in yet) computed from
    `content_plan_stage_transitions`, and a content-production-per-month bar chart for the last six
    months rendered as plain CSS bars (no charting library added, consistent with Internal Linking
    Center's deterministic server-side SVG graph elsewhere in this codebase).
  - A **shared filter bar** (workflow stage / language / author / category / tag / priority /
    publication status — `none`/`draft`/`scheduled`/`published`, the first meaning "no `contentable`
    yet") and a global title **search** apply identically across Kanban/Calendar/Table via one
    `baseQuery()` method — the three views never filter independently.
- **AI Studio integration never runs generation automatically — it only navigates.** This is a direct
  consequence of the already-established rule (Section 23/29) that AI generation is always an explicit
  admin click inside `AiAssistantPanel`. "Generate Draft" (shown on a card only while `contentable_id`
  is null) calls `ContentPlan::moveToStage()` into `ai_draft` (materializing the record) and then
  redirects the browser straight to that record's `EditArticle`/`EditPage` screen — where the existing
  embedded AI Assistant sidebar is immediately available. Once a card has content, an "AI Assistant →"
  link takes the admin straight to the existing standalone `AiContentAssistant` page
  (`?article=ID`/`?page=ID` — already documented in Section 6 as a deliberate "fallback/deep link"
  entry point, exactly the use case this is). Both paths are pure navigation over 100% pre-existing AI
  Studio code; **no new AI/LLM call of any kind was added by this feature.**
- **Testing**: `tests/Feature/TagsTest.php`, `tests/Feature/ContentPlanTest.php` (stage seeding,
  `moveToStage()` transitions/idempotency, materialization incl. slug de-duplication and the Article/Page
  branch, `effectivePublishDate()`, `PublishDueArticles`' new sync behavior, the activity log entry,
  the stage-delete DB guard), `tests/Feature/EditorialNotificationsTest.php` (assignee/author fallback,
  no-recipient no-op, review/publishing notification firing, opt-out preferences, the Filament-message
  shape, the deadline command's 24h window/exclusion/dedup/reset), `tests/Feature/WorkflowStageResourceTest.php`,
  `tests/Feature/ContentPlanResourceTest.php`, `tests/Feature/ContentPlannerKanbanTest.php`,
  `tests/Feature/ContentPlannerCalendarTest.php` (incl. the redirect and both chip-kind reschedule
  paths), `tests/Feature/ContentPlannerTableDashboardTest.php` (incl. the averaging math), and
  `tests/Feature/ContentPlannerAiIntegrationTest.php`.

## 26. Brand Memory & AI Prompt Personalization

A central, permanent knowledge base about the brand that every AI Studio feature automatically
reads — the admin writes it once, and it is never pasted into a prompt by hand again. This is
purely additive over Section 23/24's existing AI Content Assistant/provider layer: nothing about
`ActionRegistry`, `ProviderManager`, or any of the three jobs (`RunAiContentGeneration`,
`ProcessAiChatMessage`, `TranslateArticleDraft`) changed — Brand Memory injects itself at the one
place every one of them already funnels through.

- **`App\Models\BrandMemorySection`** is a labeled slot of brand knowledge (e.g. "Mission",
  "Forbidden Words", "Newsletter Tone") — `key` (unique slug), `label`, `group` (a free-text
  category used only for visual grouping — "Identity", "Voice & Audience", "Content Rules",
  "Vocabulary", "Business Info", "Credentials", "Channel Tone"), `description` (admin-facing help
  text), `is_enabled` (excluded from every prompt when off, without deleting its content),
  `is_system` (seeded rows — can be disabled but never deleted; the delete guard is enforced both
  in the UI, via the `deleteSection` Filament action, and inside the action's own query, matching
  `WorkflowStage`'s "seeded defaults, admin can add more" precedent from Section 25). A seed
  migration creates 25 default sections across the seven groups above — matching every item the
  business originally asked for (Brand Name, Mission, Vision, Writing Tone, Target Audience,
  Writing/SEO/Internal-Linking/EEAT/CTA/FAQ/Schema Rules, Forbidden/Preferred Words, Formatting
  Rules, Products, Services, Locations, Biography, Certificates, Experience, Newsletter Tone,
  Social Media Tone) plus "Languages" as a 25th descriptive section. Unlike `WorkflowStage`, none
  of these seeded slugs are special-cased anywhere in code — every section (system or custom) is
  read identically by `BrandMemoryService`; `is_system` only ever gates deletion.
- **`App\Models\BrandMemoryValue`** holds one section's content in one language — unique on
  `(brand_memory_section_id, locale)`. **Three locales are supported for Brand Memory *values*
  specifically: `en`, `tr`, and `fa` (Persian)** — this is a deliberate, narrower scope than a
  real site content locale (confirmed with the user): Persian has **no** presence anywhere else in
  this app (no `/fa` routes, no `Article`/`Page` locale support, not a `translate` target) — it
  exists solely so an admin can write brand reference text in Persian for the AI to read, not to
  serve a Persian version of the site. Do not treat this as a first step toward a third site
  locale without a separate, explicit decision (see Multilingual Architecture and Important
  Project Decisions). **Version history reuses `spatie/laravel-activitylog`** (already a project
  dependency, same trait `Article` uses) via `logOnly(['content'])->logOnlyDirty()` — no bespoke
  versions table. Note this package's v5 shape: changes land on `Activity::attribute_changes`
  (`{attributes, old}`), **not** the separate `properties` column that `withProperties()`/older
  activitylog docs describe — this was verified against this project's actual installed version
  before building the History panel, not assumed from memory.
- **`App\Services\BrandMemory\BrandMemoryService::buildContext(string $locale = 'en'): string`**
  is the single place that composes every enabled section into one text block, grouped exactly
  like the admin UI (`## {Group}` headers, `- {Label}: {content}` lines). For each section it
  reads the requested locale's value, falling back to `en` if blank, and skips the section
  entirely (no empty-labeled line) if both are blank — same "omit rather than show a placeholder"
  ethos as `Media::warnings()`/`SuggestionEngine`'s null-over-a-guess convention elsewhere in this
  codebase. **Returns an empty string when nothing is configured** — this is what makes the
  feature fully backward-compatible: every existing AI Assistant test in
  `AiContentAssistantTest`/`AiAssistantPanelTest` passes unchanged against a fresh install with no
  Brand Memory content, because the composed prompt is byte-identical to before this feature.
- **Injection point: `App\Services\AiAssistant\ContentAssistantService`'s three system-prompt
  builders, and only there.** `buildSystemPrompt()` (used by `generate()`, i.e. every
  `ActionRegistry` field — SEO/meta/excerpt/FAQ/body/tags/ALT text/etc.), `buildIntentSystemPrompt()`
  (used by `classifyIntent()`, the AI Chat router), and `buildTranslateSystemPrompt()` (used by
  `buildTranslationPayload()`) each call a new shared private `withBrandMemory(string $prompt,
  string $locale): string` helper that appends `BrandMemoryService::buildContext($locale)` when
  non-empty. The locale passed through is the **content's own locale** (`$record->locale ?? 'en'`)
  at every call site — Brand Memory just automatically speaks the same language as the content
  being worked on. This is the *only* place any of this composition happens; nothing was
  duplicated into the jobs, the Livewire panel, or `ArticleImportService`. Every AI feature listed
  in the original request (Generate/Rewrite/Translate/FAQ/SEO/Excerpt/Meta/Titles/ALT/Caption,
  plus Pages, since `ActionRegistry` already scopes most fields to both `Article` and `Page`) picks
  this up automatically with zero per-feature code — confirmed by the full pre-existing AI
  Assistant test suite passing unmodified. **"Newsletter" is a reserved section only, by explicit
  user decision** — there is no AI-generation feature for newsletter content anywhere in this
  codebase today (no `ActionRegistry` field, no AI-assist button on the newsletter composer); the
  `newsletter_tone`/`social_media_tone` sections exist so that whenever such a feature is built
  later, it can call the exact same `BrandMemoryService::buildContext()` and it will already work
  — building the newsletter AI feature itself was explicitly out of scope for this change.
- **`App\Filament\Pages\BrandMemory`** (nav "Brand Memory", AI Studio group) follows
  `AiActionRouting`'s established "Filament Page + Schema form, manual `mount()`/`save()` over a
  plain `$data` array" pattern (Section 6) rather than a bespoke Livewire screen — sections grouped
  under collapsible `Section` components, each with an "Included in AI prompts" toggle and an
  EN/Turkish/Persian `Tabs` block (`Filament\Schemas\Components\Tabs`) of `Textarea`s. `save()`
  skips persisting a still-blank value that never had a row (no needless empty `BrandMemoryValue`
  rows/activity-log noise on first save) and only ever writes through the Eloquent model (never
  the query builder), so the activity log is populated correctly. Two page-level header actions
  (`Filament\Actions\Action` with `->schema([...])->action(function (array $data) {...})`, the
  same pattern `DraftQueue`/`ImportLogResource` already use) handle "Add Custom Section" (label +
  group, with a `datalist()` of existing group names so new sections can join an existing group or
  start a new one) and "Delete Custom Section" (a `Select` scoped to `is_system = false`, plus the
  guard inside the action body described above).
  - **Version history and Preview Prompt are deliberately plain Livewire state, not nested
    Filament Actions.** An earlier attempt attached a `History` action directly to each of the 25
    per-section `Schema\Section` components via `->headerActions([...])` — this rendered fine but
    turned out to be unreachable through Livewire's `callAction()` testing helper (schema-embedded
    actions are not part of the page's own cached-actions registry the way page-level
    `getHeaderActions()` entries are). Rather than fight an unfamiliar internal API, the page
    exposes plain public methods/properties instead: `viewHistory(int $sectionId)` sets
    `$historySectionId`, a computed `getHistoryActivitiesProperty()` queries
    `Spatie\Activitylog\Models\Activity` for that section's values across all three locales, and
    the Blade view conditionally renders a history panel — exactly this project's existing
    "custom Livewire state + Blade, not a deep component tree" convention already used by
    `SeoCenter`/`MediaLibrary`/`InternalLinkingCenter`. The trigger itself is a
    `Filament\Schemas\Components\Html` component with a raw `wire:click="viewHistory(...)"`
    button (Filament schemas support arbitrary HTML content via `Html::make()` — this is what let
    the trigger stay visually inline with each section without going through the Actions
    subsystem at all). **Restoring** a version writes `activity.attribute_changes['attributes']['content']`
    (the content *as of* that version, not `['old']`, which would restore to one step *before* it)
    back through the Eloquent model — this is itself a normal, logged write, so history is
    strictly append-only and a restore can always itself be undone by restoring an earlier entry.
    "Preview Prompt" follows the same lesson but stays as a real page-level header action (since
    those *are* reachable via `callAction()`, confirmed by the passing `addSection`/`deleteSection`
    tests) — it just stores its result (`$previewPromptResult`) in a plain property and renders it
    in the Blade body rather than in the action's own modal, since the goal is a persistent,
    readable block rather than a transient dialog.
- **`ContentAssistantService::previewSystemPrompt(string $field, string $mode, string $locale =
  'en'): string`** is a thin public wrapper that calls the exact same private
  `buildSystemPrompt()`/`buildTranslateSystemPrompt()` methods `generate()`/`buildTranslationPayload()`
  use internally — there is no second, parallel prompt-construction path for the preview feature.
  It never calls a real AI provider and never writes to any record; it exists purely so "Preview
  Prompt" shows the *actual* prompt, not a simulated approximation.
- **Testing**: `tests/Feature/BrandMemoryTest.php` (`BrandMemorySection`/`BrandMemoryValue` model
  behavior, `BrandMemoryService::buildContext()`'s grouping/locale-fallback/disabled-section
  exclusion, and — critically — that `ContentAssistantService::generate()`'s outbound HTTP request
  body both includes configured Brand Memory content and stays byte-identical to before this
  feature when nothing is configured) and `tests/Feature/BrandMemoryPageTest.php` (the Filament
  page: load, save incl. the no-op-blank-value case, add/delete custom section incl. the
  system-section delete guard, view/close history, restore-writes-back-and-logs-a-new-version, and
  Preview Prompt).

## 27. Knowledge Base for AI Content Generation

A structured library of standalone facts/documents about the brand/business that every AI Content
Assistant generation call (Section 23) automatically searches and pulls relevant entries from
before writing — reusing Brand Memory's provider layer and injection pattern, but solving a
different problem: Brand Memory is one fixed block always appended in full (mission, tone, forbidden
words, ...); Knowledge Base is a growing, filterable set of individual facts (a location's address,
one course's curriculum, a specific policy) where only the ones relevant to *this specific
generation* should be used — appending all of it, unfiltered, would flood the prompt and dilute
relevance as the library grows. Nothing about `ActionRegistry`, `ProviderManager`, Brand Memory, or
any of the three AI jobs was rebuilt to add this — it is purely additive, injected at one new point
inside `ContentAssistantService::generate()`.

- **`App\Models\KnowledgeEntry`** is one retrievable fact or document — `title`, `category` (free
  text with a suggested-category datalist in the admin form: Biography, Services, Policies, Courses,
  Martial Arts, Locations, FAQs, Products, Business Information, Contact Information, Training
  Methods — not a closed enum, admins can type anything, matching `Article.category`'s existing
  free-text convention), `locale` (**`en`/`tr` only** — deliberately narrower than Brand Memory's
  `en`/`tr`/`fa`, because Knowledge Base content feeds directly into generated site content, which
  is only ever en/tr, unlike Brand Memory's `fa` carve-out which is reference-only and never
  retrieval-scored, see Section 26), `content` (the actual fact text), `source` (optional, admin's
  own reference — where this came from), `status` (`draft`/`active`/`archived` — only `active` is
  ever retrieved), `priority` (`low`/`medium`/`high`/`critical` — a scoring input, see below),
  `is_pinned` (bypasses relevance scoring entirely, see below), `expires_at` (optional — an expired
  entry is excluded automatically, no cron needed since expiry is just a `where` clause evaluated at
  retrieval time). **One row per language**, mirroring `Article`'s two-row-per-translation model
  (Multilingual Architecture) — deliberately **not** Brand Memory's newer per-field-per-locale
  substructure (`BrandMemorySection` + `BrandMemoryValue`); each `KnowledgeEntry` is a single-language
  document, which is simpler and fits the "individual fact" shape better than Brand Memory's
  "one labeled slot translated into several languages" shape. Version history reuses
  `spatie/laravel-activitylog` (`LogsActivity`, same trait `Article`/`BrandMemoryValue` use) but
  surfaces in the **existing, generic** `ActivityLogPage` — unlike Brand Memory's bespoke
  per-section history panel, `KnowledgeEntry` needed no new history UI at all, since
  `ActivityLogPage`'s `Activity::query()` table already works for any subject with a `title`
  attribute.
- **`App\Models\KnowledgeEntryAttachment`** — a PDF/document attached to an entry (`disk_path`,
  `original_filename`, `mime_type`, `size`), plain `hasMany`, cascade-deleted with its parent.
  **Deliberately bypasses `MediaProcessor`** (Section 21) — that pipeline is image-only
  (WebP/thumbnail/responsive variants via `intervention/image`), the wrong tool for a PDF or Word
  document; attachments here are just stored as-is on the `public` disk under `knowledge-base/`,
  uploaded via a plain `FileUpload` field (not through the DAM), then converted into
  `KnowledgeEntryAttachment` rows by `KnowledgeEntryAttachment::createManyFromDiskPaths()` (called
  from both `CreateKnowledgeEntry::afterCreate()` and `EditKnowledgeEntry::afterSave()` so this
  conversion logic exists once, not duplicated per page). Attachments are for admin/AI *reference*
  only — nothing currently parses a PDF's contents into the AI prompt; `content` is what the AI
  actually reads.
- **Tags are the exact same `Tag` model/`taggables` pivot Article/Page/ContentPlan already use** —
  `KnowledgeEntry::tags(): MorphToMany` / `Tag::knowledgeEntries(): MorphToMany`, no new tagging
  system, matching Section 25's "don't build a second tagging mechanism" precedent.
- **`App\Services\KnowledgeBase\KnowledgeBaseService::retrieveRelevant(query, locale, limit = 5)`**
  is the only retrieval entry point, and this is where "no vector database" was solved without
  reinventing search from scratch:
  1. **Pinned entries for that locale are always included first**, regardless of relevance — for
     must-know facts (business name, core policy) that should never depend on a scoring heuristic
     guessing right.
  2. If pinned entries alone don't fill `limit`, the remaining `active`, non-expired, non-pinned
     entries in that locale are **cheaply pre-filtered** by a hand-rolled keyword-overlap score
     (significant-word intersection between the query and the entry's title/category/content, plus a
     tag-match bonus and a priority weight — the same small, self-built scoring style as
     `SuggestionEngine`'s Jaccard-style internal-link scoring, Section 22 — not extracted/shared from
     it, since it's a genuinely different scoring problem; a new, independent implementation was
     simpler and more honest than forcing reuse of private methods built for link suggestions). This
     shortlist is capped at 15 candidates.
  3. That shortlist (never the whole table) is handed to the **existing**
     `App\Services\AiAssistant\ProviderManager` — the same abstraction every other AI feature already
     goes through (Section 24) — with a small system prompt asking it to return only the genuinely
     relevant entry IDs as JSON, most relevant first. **This is what "semantic retrieval" means in
     this codebase**: the already-configured AI provider judges relevance over a cheap pre-filtered
     list, instead of adding a vector-embedding pipeline and a new dependency — directly consistent
     with this project's established anti-dependency stance (`DiffService`'s hand-rolled diff,
     `SuggestionEngine`'s hand-rolled scoring, Section 13's "no dependency for something a few lines
     of code can do").
  4. **If no AI provider is configured, or the call fails or returns unparseable output, retrieval
     silently falls back to the keyword-only shortlist** — content generation must never be blocked
     or degraded by a Knowledge Base retrieval failure. The AI explicitly returning an empty array
     (genuinely "nothing here is relevant") is honored as a real, deliberate zero-result and is
     **not** treated as a failure that triggers the fallback — the two cases are kept distinct on
     purpose (see the two dedicated tests in `KnowledgeBaseRetrievalTest.php`).
- **Injection point: `ContentAssistantService::generate()`, and only there** — not `classifyIntent()`
  (the AI Chat router, Section 23) and not `buildTranslationPayload()`/`buildTranslateSystemPrompt()`
  (translation must preserve the *source* content's facts exactly, not have new fact candidates from
  a different retrieval pass mixed in). A private `relevantKnowledgeFor()` builds the retrieval query
  from the record's title + the field's label (e.g. "Guard Passing Basics — SEO Title"), skips
  retrieval entirely for `content_review_summary` (it only summarizes existing findings, it isn't
  writing new content that needs supporting facts) and for any locale outside `en`/`tr`. A private
  `withKnowledgeContext()` appends a `## Relevant Knowledge Base Facts` block to the system prompt
  only when entries were actually found — an install with an empty Knowledge Base produces a
  byte-identical prompt to before this feature, the same backward-compatibility posture Brand Memory
  established in Section 26. `generate()`'s return shape gained one new key,
  `knowledge_entry_ids: int[]`, alongside the existing `result`/`warnings`.
- **"Which knowledge was used" is tracked per generation, not just logged.** A new pivot table,
  `ai_generation_knowledge_entry` (mirrors `taggables`'s own shape: plain `id` + two FKs + timestamps
  + a unique constraint on the pair — **the unique index needs an explicit short name**, since the
  auto-generated one for this table+column combination exceeds MySQL's 64-character identifier
  limit (SQLSTATE 42000/1059); this was missed when the table first shipped and fixed by a
  `2026_07_17_000000` fix-up migration, same "give it a short name / add a corrective migration for
  already-affected installs" lesson as `internal_link_suggestions` and `notifications` elsewhere in
  this file — do not remove the explicit name from either migration), backs
  `AiGeneration::knowledgeEntries(): BelongsToMany` / `KnowledgeEntry::generations(): BelongsToMany`.
  `App\Jobs\RunAiContentGeneration` syncs `generate()`'s returned `knowledge_entry_ids` onto the
  `AiGeneration` via this pivot immediately after a successful completion (never on failure/
  cancellation) — a small, additive step in the same job, not a second poller. `AiAssistantPanel`'s
  Generate-tab field cards and the History tab both render a "📚 Knowledge used: ..." line whenever
  a generation's `knowledgeEntries` relation is non-empty, so an admin can always see *why* the AI
  wrote what it wrote, not just the output — this was an explicit requirement ("display which
  knowledge entries were used for every AI generation"), not a nice-to-have.
- **`App\Filament\Resources\KnowledgeEntries\KnowledgeEntryResource`** (nav "Knowledge Entries", its
  own "Knowledge Base" nav group — deliberately not nested under "AI Studio", since this is a content
  library an admin curates directly, not an AI Studio pipeline screen) — an ordinary CRUD resource
  following `TagResource`'s exact file layout (`Schemas/KnowledgeEntryForm.php`,
  `Tables/KnowledgeEntriesTable.php`, `Pages/{List,Create,Edit}KnowledgeEntry.php`), the first feature
  in this project's Knowledge/AI Studio family that needed **no** custom Livewire page — a standard
  Filament Resource was sufficient. Table filters: locale, category (dynamic, built from distinct
  values actually in use — same pattern as `ContentPlanTable`'s category filter), status, priority,
  tag, and a pinned `TernaryFilter`.
- **Testing**: `tests/Feature/KnowledgeBaseTest.php` (model behavior — defaults, the `available`
  scope, `isExpired()`, the `Tag`/attachment relations, activity-log version history, the
  `AiGeneration` pivot), `tests/Feature/KnowledgeBaseRetrievalTest.php`
  (`KnowledgeBaseService::retrieveRelevant()` directly — pinned-always-included, expired/inactive
  exclusion, locale scoping, the keyword fallback, AI ranking via `Http::fake()`, the
  empty-array-vs-failure distinction, limit handling across pinned+ranked entries),
  `tests/Feature/KnowledgeBaseGenerationIntegrationTest.php` (`ContentAssistantService::generate()`
  actually injecting relevant knowledge into the outbound prompt and returning the right
  `knowledge_entry_ids`, the `content_review_summary`/cross-locale exclusions,
  `RunAiContentGeneration` persisting — or correctly not persisting — the usage pivot),
  `tests/Feature/KnowledgeEntryResourceTest.php` (the Filament resource: list/create/edit/delete,
  attachment upload registering a real `KnowledgeEntryAttachment` row, the locale filter), and
  `tests/Feature/KnowledgeBaseMigrationFixTest.php` (the `ai_generation_knowledge_entry` pair is
  actually unique at the DB level, and the `2026_07_17_000000` fix-up migration is idempotent).

## 28. One Click Publish (AI Import)

An upgrade of the existing paste-in AI Import pipeline — same service, same UI page, extended rather
than rebuilt, per the explicit "inspect first, extend, don't rebuild" requirement. Before this
feature, `ArticleImportService` accepted JSON or Markdown, mapped onto a fixed set of Article fields,
and skipped tags/SEO-title/OG entirely. It now accepts five auto-detected formats, actually consumes
every field that has a real CMS home, lets an admin manually correct anything before the final
import, and surfaces (without storing) every field that still has none.

- **Five auto-detected formats, all funneling into the same unmodified `normalizeAndValidate()`.**
  `ArticleImportService::detectFormat()` picks, in order: the custom `[[FIELD]]` bracket-marker
  format (checked first, since a marker like `[[TITLE]]` would otherwise misdetect as JSON's leading
  `[`), JSON, this project's own `<article>`-rooted XML schema, HTML (full document or plain
  fragment), then Markdown as the catch-all. Each format has its own `parseX()` method that produces
  the *same* associative-array shape JSON already did — `parseXml()`, `parseHtml()`, and
  `parseCustomMarkers()` are new, `parseJson()`/`parseMarkdown()` are unchanged — so `analyze()`'s
  single call to `normalizeAndValidate()` never needed to change, and every existing validation rule
  (slug uniqueness, FAQ shape, publish-date logic, etc.) applies identically regardless of input
  format. A shared `parseKeyValueBlock()` (extracted from Markdown's front-matter parser) is reused
  by HTML's optional leading `<!-- field: value -->` metadata comment, so that "field: value" parsing
  logic exists exactly once. XML parsing uses `$xml->xpath('tags/tag')`-style calls, never the
  `$parent->child` magic property, for any element that can repeat — accessing `$xml->tags->tag`
  directly is broken two different ways in SimpleXML (a single matching child returns that element
  itself, not a one-item collection, so iterating it iterates *its* children instead; multiple
  matching children iterate with the *tag name* as the array key, so `iterator_to_array()`
  silently keeps only the last one) — both bugs were caught by this feature's own tests, not assumed.
- **Real SEO/OG columns are now populated, not skipped or silently repurposed.** Before this feature,
  `seo.title` was unconditionally reported as skipped ("page titles are always built from the article
  title") and `seo.meta_description` only ever filled a blank `excerpt` — both predate the real
  `seo_title`/`meta_description`/`og_title`/`og_description` columns added in Section 23. Now
  `seo.title`/`seo.meta_description`/`og.title`/`og.description` (or their flat
  `seo_title`/`meta_description`/`og_title`/`og_description` equivalents, see manual corrections
  below) write directly to those real columns when provided, while `excerpt`'s own blank-fallback
  behavior (reusing the SEO description when no excerpt was given) is preserved unchanged. Leaving
  them blank still means the public templates fall back to title/excerpt exactly as before — nothing
  about the existing fallback columns/rendering changed, only what the importer now fills in.
- **Tags and target keywords are real now.** `tags` (previously always reported as skipped — "no
  per-article tags field yet", written before Section 25 added `Tag`) now creates/attaches real `Tag`
  rows via `Tag::firstOrCreate(['name' => ...])` + `$article->tags()->sync(...)`, exactly like
  `ArticleForm`'s tags field. A new `seo.keywords` (or top-level `keywords`) creates real `Keyword`
  rows the same way `ArticleForm`'s keyword repeater does — feeding Internal Linking Center's
  suggestion scoring (Section 22) for free.
- **Featured images go through `MediaProcessor`, not a bespoke raw `Media::create()`.** The importer
  used to hand-roll its own `Media` row with no WebP/thumbnail/responsive derivatives — the one
  upload path in the whole app that bypassed the DAM pipeline (Section 21). `downloadImage()` now
  wraps the downloaded temp file in a manually-constructed `Illuminate\Http\UploadedFile` (the
  standard Laravel `test: true` pattern for a programmatically-created file — the same one
  `UploadedFile::fake()` uses) and calls `MediaProcessor::store()`, so an imported featured image
  gets the exact same derivatives and DAM tracking as any other upload. A new `image_alt` field sets
  `Media::alt_text` on the resulting (or reused, for an existing `featured_image` path) `Media` row.
- **AI-declared internal link suggestions land in the existing table, through the existing lifecycle
  — never a second insertion path.** An `internal_links` array (target slug/id + anchor text +
  reason) becomes `pending`/`origin=ai` rows in `internal_link_suggestions`, via the same shape
  `AiAssistantPanel::applyInternalLinkSuggestions()` (Section 23) already writes — approval still
  only ever happens through Internal Linking Center's `insertLinkForSuggestion()`. **Deliberately does
  NOT auto-dispatch `GenerateInternalLinkSuggestions`** (the rule-based regeneration job) after a
  successful import, even though that would seem like the obvious way to also seed rule-based
  suggestions for the new article: `SuggestionEngine::generateAndPersist()` deletes *any* `pending`
  suggestion that its rule-based rescoring doesn't independently reconfirm, regardless of `origin` —
  dispatching it in the same request that just created these `origin=ai` rows could delete them
  before an admin ever sees them. This was caught by a failing test during development, not
  discovered in production. Rule-based suggestions for a freshly imported article still only come
  from Internal Linking Center's existing manual "Generate suggestions" button.
- **`external_links` are surfaced, never stored** — same "suggestion only" posture as the AI Content
  Assistant's own `external_links` field (Section 23): each URL is checked live via
  `SeoAuditService::checkUrls()` before being shown in the Preview panel, exactly like
  `AiAssistantPanel::getVerifiedExternalLinksProperty()` already does, but nothing is ever persisted.
- **Fields with genuinely no CMS home are detected and reported, never silently dropped or stored
  anywhere new** — `schema`/`canonical`/`robots` (unchanged, pre-existing "auto" bucket: the site's
  SEO system already generates these), plus newly detected `image_caption`, `cta`
  (call-to-action text), `twitter` (Twitter Card), and `featured_image_prompt` (an AI image-generation
  prompt, not an image) all land in the mapping report's `skipped` bucket with a plain-language reason
  — this mirrors the AI Content Assistant's existing "`caption`/`schema` are suggestion-only, never
  auto-applied" stance (Section 23) rather than inventing new storage for them.
- **`newsletter_summary` is not a recognized field at all, by explicit user decision** — it isn't in
  `ArticleImportService::ALIASES`, so a pasted response containing it just falls into the existing,
  harmless "Unknown field … was ignored" warning bucket alongside any other unrecognized key. This
  was an explicit "stop it completely, don't continue" instruction — do not add `newsletter_summary`
  handling (mapped, auto, or skipped) as a side effect of an unrelated task; see Important Project
  Decisions.
- **Manual corrections — the "allow manual corrections" requirement — via a new `$overrides`
  parameter that always wins, even over non-empty pasted content.** `analyze()`/`preview()`/`import()`
  each gained an optional trailing `array $overrides = []` (backward compatible — every existing
  caller, `AiImportController`, `ImportAiArticle`, `TranslateArticleDraft`, keeps working unchanged
  since the parameter is appended with a default). Inside `normalizeAndValidate()`, overrides are
  applied immediately after alias resolution and profile-default filling, *before* any validation
  runs — so slug-uniqueness, status/date consistency, etc. all naturally re-validate against the
  corrected values, reusing the entire existing validation pipeline unchanged rather than building a
  parallel "patch an already-validated payload" path. On the `AiImport` page, a collapsible "Manual
  corrections" `Section` is auto-filled from the last successful Preview's payload
  (`loadCorrectionsFromPayload()`) — title/slug/category/status/publish date/author/excerpt/SEO
  title/meta description/OG title/OG description/tags — and `correctionOverrides()` converts only the
  actually-filled fields into the flat key shape `ArticleImportService` expects (new flat
  `seo_title`/`meta_description`/`og_title`/`og_description` `ALIASES` entries exist specifically for
  this — the raw AI content still uses the nested `seo`/`og` objects as before; the flat keys are for
  this override layer only). A blank correction field is never sent as an override, so it can never
  clobber a real parsed value — matches the existing "blank profile default doesn't win" precedent.
- **Rollback finally has a button.** `ArticleImportService::rollback()` existed since the original AI
  Import feature but was never wired to any Filament UI (confirmed dead code from a UI perspective).
  The `AiImport` page's Recent Imports table now shows a "Roll back" action on any row where
  `ImportLog::canRollBack()` is true, calling the exact same unmodified `rollback()` method.
- **Testing**: `tests/Feature/OneClickPublishTest.php` (service-layer: format auto-detection for all
  five formats, XML/HTML/custom-marker parsing including the SimpleXML iteration bugs above, real
  tags/keywords/SEO/OG columns, `MediaProcessor` integration + image ALT on both the downloaded and
  reused-existing-path cases, internal-link-suggestion creation, the deliberate non-dispatch of
  `GenerateInternalLinkSuggestions`, manual overrides winning over non-empty content and never
  clobbering on blank, the always-detected-never-stored fields, and that `newsletter_summary` is a
  plain unknown field), `tests/Feature/OneClickPublishPageTest.php` (the `AiImport` page's Livewire
  behavior: all five formats offered, corrections auto-populated after Preview, a correction actually
  overriding the imported title, and the rollback action). `tests/Feature/AiImportTest.php`'s
  `test_tags_and_seo_extras_warn_but_do_not_block` was renamed and updated to assert the new
  intentional behavior (tags/`og.title` now `mapped`, not `skipped`/`auto`) — everything else in the
  pre-existing `AiImportTest.php`/`AiStudioTest.php`/`AiImportApiTest.php` suites passes unchanged,
  confirming this was a real extension, not a rebuild.

## 29. AI Agent — Proactive Content Audits

A dashboard that continuously analyzes the whole site (weekly automatically, or on demand) and
surfaces actionable recommendations — "articles needing updates," "missing FAQ opportunities,"
"orphan pages," and so on — instead of waiting for an admin to ask. Per the explicit build
requirement ("Do NOT rebuild existing functionality. Reuse everything already implemented. Only
build the AI Agent layer."), this feature adds **no new detection logic where an existing service
already has the check** — it is a thin orchestration + persistence + UI layer over
`SeoAuditService`, `LinkGraphService`, `ContentReviewService`, `ActionRegistry`/
`ContentAssistantService`/`ProviderManager`, `AiGeneration`/`RunAiContentGeneration`,
`TranslateArticleDraft`, and `InternalLinkSuggestion` — all used exactly as they already existed.

- **`App\Services\AiAgent\AgentAuditService`** is the detection engine — one private method per
  category, `run(): array<string, array>` returns all sixteen at once, nothing is written (pure
  and testable, same posture as `SuggestionEngine::suggest()`). Of the sixteen requested
  categories, **nine are direct reuse of an existing service's own check** — no new detection code
  at all: `missing_internal_links` (`LinkGraphService`'s outbound-count-zero nodes, published only),
  `missing_faq`/`missing_cta` (`ContentReviewService::review()`'s existing `missing_faq_opportunity`/
  `weak_cta` findings), `missing_alt`/`broken_links`/`missing_schema`/`orphan_pages`
  (`SeoAuditService::run()`'s own categories, wrapped as-is), `poor_seo`
  (`ContentReviewService::scoreCard()`'s existing `seo` category, thresholded at 70). **Seven are
  genuinely new, small heuristics** (this project's established "hand-rolled, explainable, no new
  dependency" style — same ethos as `SuggestionEngine`'s Jaccard scoring and
  `KnowledgeBaseService`'s keyword pre-filter): `content_refresh` (published content not updated in
  180 days — this single signal covers **both** "Articles needing updates" and "Content that should
  be refreshed" from the original request, since they are the same thing under two names, following
  the same "one honest signal, not two fake ones" posture as `SeoAuditService::missingCanonicals()`),
  `weak_intro`/`weak_conclusion` (first/last paragraph word count below a threshold, via the
  existing `HtmlContentScanner::paragraphs()`), `thin_content` (published body under 300 words),
  `duplicate_topics` (Jaccard title-word-overlap ≥ 0.4 within the same model+locale, excluding
  translation pairs in either direction), `content_cannibalization` (two-or-more published Articles
  sharing the same `Keyword` value in the same locale — reuses Section 22's existing `Keyword`
  model, not a new keyword system), `needs_translation` (published content with no `translation_of`
  link in either direction). `image_optimization` reuses `Media::warnings()` (Section 21) on
  in-use media, excluding the ALT warning specifically (that's `missing_alt`'s job, avoiding a
  duplicate finding for the same underlying fact).
- **Small, additive extensions were needed to make reuse possible, not a rebuild of the reused
  services.** `SeoAuditService::finding()` and every hand-built finding array in that service
  (the `Media`-branch of `missingAlt()`, the blog-index entries in `missingSchema()`,
  `allLinkSources()`'s per-source `meta`, and `checkExternalLinks()`'s finding array) gained one new
  key, `'id'` — the underlying record's own id (or `null` where there genuinely isn't one, e.g. the
  blog index) — so `AgentAuditService` can route a finding back to a fixable record without
  re-deriving what `SeoAuditService` already knew. This is purely additive (an extra array key
  nothing else reads) and is covered by the full pre-existing `SeoAuditTest.php`/
  `InternalLinkingTest.php` suites passing unchanged. `Media::ownerRecord()` was added as the
  reverse of the existing `Media::forRecord()` (record → media; the new method goes media → owning
  Article/Page by `disk_path` match, the same string-matching DAM convention documented in Section
  21) — this is what lets a "missing ALT text" finding on a Media-tracked featured image resolve to
  a fixable `alt_text` recommendation rather than being merely reported.
- **`App\Services\AiAssistant\GenerationApplier`** is a small, necessary extraction, not new write
  logic: the write bodies of `AiAssistantPanel::applyGeneration()`/`::restoreGeneration()`/
  `::applyInternalLinkSuggestions()` (Section 23) moved into this class **verbatim**, so both the
  editor sidebar and this new Agent dashboard call the exact same write path instead of a second
  copy of it. `AiAssistantPanel`'s three methods are now thin wrappers (guard + call the service +
  show a notification) — confirmed behavior-identical by the full pre-existing
  `AiAssistantPanelTest.php`/`AiContentAssistantTest.php` suites (192 assertions) passing unchanged
  after the extraction. This is the one place this feature touches Section 23's code, and it does
  not change what may write or when — see "Must Never Be Changed" below.
- **`App\Models\AiAuditRun`** (one row per audit — manual or scheduled — with `found_count`/
  `new_count`/`resolved_count`, mirroring `ImportLog`'s "one history row per run" shape) and
  **`App\Models\AiRecommendation`** (one row per finding, `pending`/`applied`/`rejected`, unique on
  `category+content_type+content_id+related_content_type+related_content_id+locale` with an
  explicit short index name — `ai_recommendation_unique` — applying the `ai_generation_knowledge_entry`
  MySQL-64-character lesson from Section 27 proactively this time, not as a later fix-up migration.
  The uniqueness columns use `''`/`0` defaults rather than `NULL` **on purpose**: MySQL/SQLite both
  treat every `NULL` as distinct within a unique index, so a nullable `content_id` would have made
  `upsert()` insert a fresh duplicate row on every audit run for any finding without a specific
  record (e.g. the blog-index "Missing Schema" entries) instead of updating the existing one.
- **`AgentAuditService::generateAndPersist()`** upserts fresh `pending` rows and deletes stale
  `pending` rows no longer reconfirmed — **and never touches `applied`/`rejected` rows**, the exact
  same invariant `SuggestionEngine::generateAndPersist()` already established for
  `internal_link_suggestions` (Section 22) — an admin's decision on a recommendation is permanent
  history, not something a re-run can silently undo. Every run also creates an `AiAuditRun` history
  row (`status: running → completed`/`failed`), so nothing about "did an audit run, and what
  happened" depends only on the recommendation rows' current state.
- **One-click fixes reuse the existing generate → preview → apply pipeline end-to-end, they do not
  add a second way for AI output to reach a record.** `App\Services\AiAgent\AgentFixService` has
  exactly three methods: `queueFix()` creates an `AiGeneration` and dispatches the **same**
  `RunAiContentGeneration` job every `ActionRegistry` field already uses (or, for a
  `needs_translation` recommendation, the **same** `TranslateArticleDraft` job Section 23's Translate
  feature already uses) — nothing here is a new generation mechanism, only a new caller. `approveFix()`
  is only callable once that generation's `status === 'completed'`, and it writes via
  `GenerationApplier::apply()`/`::applyInternalLinkSuggestions()` — the identical write path the
  editor sidebar uses — then marks the recommendation `applied`. For a `translate`-type
  recommendation, `approveFix()` writes nothing at all (the translated draft is already a real,
  separately-saved `Article`/`Page` row by the time the generation completes, exactly as it already
  works in the editor's Translate card) and only marks the recommendation resolved. `rejectFix()`
  needs no generation at all — it works for review-only recommendations too (dismiss). Every
  recommendation's `fix_type` is one of three values: `field` (a normal `ActionRegistry` field/mode,
  the common case), `internal_links` (routes into the **existing**, unmodified
  `internal_link_suggestions`/Internal Linking Center approve lifecycle, Section 22 — never a second
  insertion path), or `translate` (routes into the **existing** Translate feature); `null` means
  **review-only, by design** — `broken_links`, `missing_schema`, `orphan_pages`,
  `image_optimization`, `duplicate_topics`, and `content_cannibalization` all have `fix_type: null`
  on purpose, because each of those requires an editorial/technical decision (which href to fix,
  wiring a template, merging two articles, resizing an image) that this project has already decided
  elsewhere is out of scope for AI to decide unattended — see "Important Project Decisions" below.
- **`App\Filament\Pages\AiAgentDashboard`** (nav "AI Agent", AI Studio group, the last item in that
  group's nav order) follows the exact same "custom Page, sixteen-category sidebar with counts,
  filter toolbar, per-item action buttons" shape `SeoCenter`/`InternalLinkingCenter` already
  established (same `agent-*`-prefixed inline `<style>`-in-Blade convention as those pages' own
  `seo-*`/`ilc-*` prefixes) — no new UI pattern was invented. Each recommendation card offers
  Review (a link to the record's own edit page, or `related_edit_url` for pair-based findings — no
  new preview surface, it reuses the Filament edit screens that already exist), Preview Fix (calls
  `queueFix()`), Approve (calls `approveFix()`, only enabled once the linked generation is
  `completed`), and Reject (calls `rejectFix()`). `wire:poll` runs only while the latest audit is
  `running` or some linked `AiGeneration` is `queued`/`processing` — the same conditional-polling
  posture Section 23's sidebar already established, not a new pattern.
- **Weekly automatic audits reuse this project's existing scheduler infrastructure, not a new
  mechanism.** `php artisan agent:audit` (`App\Console\Commands\AgentAudit`) runs
  `AgentAuditService::generateAndPersist('scheduled')` **synchronously**, registered as
  `Schedule::command('agent:audit')->weekly()` in `routes/console.php` — the same
  synchronous-in-the-cron-process pattern `articles:publish-due` already uses, deliberately chosen
  over a queued job for the automatic path so a weekly audit doesn't silently never run on a server
  where the queue worker isn't guaranteed to be running (Deployment Workflow's documented, standing
  constraint). The dashboard's manual "Run audit now" button instead dispatches the queued
  `App\Jobs\RunAgentAudit` (same justification as `GenerateInternalLinkSuggestions`, Section 22 —
  a web request shouldn't block on an O(n²) pairwise scan), which calls the exact same
  `generateAndPersist()` method with `trigger_type: 'manual'` — one detection method, two invocation
  contexts, no duplicated audit logic between the command and the job.
- **Testing**: `tests/Feature/AiAgentTest.php` (every detector category with a positive and, where
  meaningful, a negative fixture; `generateAndPersist()`'s upsert/never-touch-applied-or-rejected/
  stale-pending-cleanup invariants; `AgentFixService::queueFix()`/`approveFix()`/`rejectFix()`
  including the `field`/`internal_links`/`translate` fix-type branches and the review-only/
  not-ready-yet failure paths; the `agent:audit` command and the `RunAgentAudit` job) and
  `tests/Feature/AiAgentDashboardTest.php` (the Filament page's Livewire behavior: rendering,
  `runAuditNow()` dispatching the queued job, category switching, pending-only category counts,
  search filtering, and the queue/approve/reject actions end-to-end). The full pre-existing suite
  (412 other tests) passes unchanged after this feature, confirming the additive extensions to
  `SeoAuditService`/`Media`/`AiAssistantPanel` really are behavior-preserving.

## 30. Performance Rules

- Database: SQLite with session/cache/queue all on the same database connection (`database` driver for all three). This is fine at current traffic; if concurrent write load grows (many simultaneous sessions + queued jobs + cache writes), moving cache/session to Redis and/or the DB to MySQL/Postgres is the first lever to pull — do not attempt in-place SQLite tuning workarounds instead.
- No caching layer sits in front of `SiteSetting` reads or the homepage/blog article queries today — each page load queries fresh. At current content volume this is not a measured problem; if you add caching here, invalidate it from the same places that already clear cache today (`PublishDueArticles`, the Filament save hooks) rather than introducing a second, parallel cache-invalidation path.
- The two maintenance routes (`system-cache-flush-*`, `system-migrate-*`) are also, incidentally, a performance/availability risk since anyone can trigger a full cache clear at will — another reason they must be fixed (see Security Rules).
- Keep `composer install --no-dev --optimize-autoloader` and `artisan config:cache`/`route:cache`/`view:cache` as standard for any production deploy (see Deployment Workflow) — these are the main have-you-done-this-yet performance checks for a Laravel app of this shape.

## 31. Testing Strategy

**Current state: almost no test coverage.** `tests/Feature/ExampleTest.php` asserts `/` returns HTTP 200, and `tests/Unit/ExampleTest.php` asserts `true === true` — both are the unmodified Laravel scaffold. The real test files are `tests/Feature/PagesModuleTest.php` (standalone Pages module: publish states, locale routing, blog/feed/sitemap isolation, Filament resource access), `tests/Feature/NewsletterTest.php` (subscribe/verify/unsubscribe flows, honeypot + time-gate bot defenses, rate limiting, locale handling, admin resource) `tests/Feature/AiImportTest.php` (AI import: JSON/Markdown parsing, validation errors, field mapping, scheduling/draft/published paths, image download into the media library, import logging, admin page access), `tests/Feature/AiStudioTest.php` (preview history, rollback, profile defaults, draft queue scoping, AI Studio resource pages), `tests/Feature/AiImportApiTest.php` (API auth, forced-draft policy, signed preview URL, dry-run validation, queueing, rate limiting, token resource), `tests/Feature/MediaLibraryTest.php` (`MediaProcessor` store/replace/delete — original kept, WebP/thumbnail/responsive derivatives generated and cleaned up, replace preserves `disk_path`; `MediaFolder` nesting + empty-only deletion; `MediaUsageScanner` against Article/Page/SiteSetting; `Media::warnings()` thresholds — all against the services directly, not the Livewire page, matching this project's existing pattern of testing the service layer rather than the Filament UI), `tests/Feature/SeoAuditTest.php` (`SeoAuditService::run()` — each of the 9 fast categories, including the always-empty missing-canonicals check and the Article-vs-Page split on missing-schema; `checkExternalLinks()` against `Http::fake()`), `tests/Feature/InternalLinkingTest.php` (`InternalLinkResolver::parseInternalPath()` including the external-URL guard; `LinkGraphService` inbound/outbound counting, dedup of repeated links to the same target, draft-inclusive no-inbound/no-outbound vs. published-only weak-linking, the always-empty redirect-chains check; `SuggestionEngine` keyword/category scoring, locale isolation, already-linked exclusion, and `generateAndPersist()`'s upsert + approved/dismissed preservation + stale-pending cleanup; the `Keyword` morphMany relation on both `Article` and `Page`), and `tests/Feature/AiContentAssistantTest.php` (`ActionRegistry` model-scoping; `NullProvider`/`AnthropicProvider` against `Http::fake()`, including the container binding switching on `services.anthropic.key`; `ContentAssistantService::generate()`'s prompt-building and response-parsing for every response shape — text/list/qa_pairs/internal_link_suggestions/external_link_suggestions, including malformed-JSON and markdown-fenced-JSON handling; `RunAiContentGeneration`'s queued→completed/failed transitions; apply/restore snapshot round-trips including the `alt_text`→`Media` special case; `AiAssistantPanel::applyInternalLinkSuggestions()` creating `origin=ai` pending rows; `ContentReviewService`'s checks against fixture HTML; `SeoAuditService::checkUrls()`), and `tests/Feature/AiAssistantPanelTest.php` (the AI Assistant sidebar redesign, Section 23: `DiffService::diffWords()`; `ContentReviewService::scoreCard()`'s six categories; the `body` field's `html` response shape; Quick Actions/`optimizeEntireArticle()` queuing the right field set; `ContentAssistantService::classifyIntent()`'s action/translate/chat routing and validation fallbacks; `ProcessAiChatMessage`; `buildTranslationPayload()` and `TranslateArticleDraft` for both Article and Page, including the `faqs: null` validation-error fix; `AiGeneration::isCancellable()`/cancellation checkpoints in both jobs; `AiGeneration::scopeForRecord()` and the History tab). The Editorial Workflow & Content Planner (Section 25) is covered by `tests/Feature/TagsTest.php`, `ContentPlanTest.php`, `EditorialNotificationsTest.php`, `WorkflowStageResourceTest.php`, `ContentPlanResourceTest.php`, `ContentPlannerKanbanTest.php`, `ContentPlannerCalendarTest.php`, `ContentPlannerTableDashboardTest.php`, `ContentPlannerAiIntegrationTest.php`, and `AdminPanelResilienceTest.php` (the admin panel, including `SystemMaintenance`, stays reachable even before this feature's migrations have run). Brand Memory (Section 26) is covered by `tests/Feature/BrandMemoryTest.php` (`BrandMemorySection`/`BrandMemoryValue` model behavior, `BrandMemoryService::buildContext()`'s grouping/locale-fallback/disabled-section exclusion, and that `ContentAssistantService::generate()`'s outbound request both includes configured content and stays byte-identical to before this feature when nothing is configured) and `tests/Feature/BrandMemoryPageTest.php` (the Filament page: load, save incl. the no-op-blank-value case, add/delete custom section incl. the system-section delete guard, view/close history, restore, Preview Prompt). One Click Publish (Section 28) is covered by `tests/Feature/OneClickPublishTest.php` (the five formats, the new real fields, `MediaProcessor` integration, internal-link-suggestion creation, manual overrides, and the always-detected-never-stored fields) and `tests/Feature/OneClickPublishPageTest.php` (the `AiImport` page's Livewire behavior) — including `PublishDueArticles`' new `ContentPlan`-sync addition specifically, though the command's own core flip-to-published logic remains covered only by this addition's tests, not a dedicated test of the pre-existing behavior (see priority list below, still open). The AI Agent (Section 29) is covered by `tests/Feature/AiAgentTest.php` (every detector category, `generateAndPersist()`'s upsert/never-touch-decided-rows/stale-cleanup invariants, `AgentFixService`'s `field`/`internal_links`/`translate` fix-type branches, `agent:audit`, `RunAgentAudit`) and `tests/Feature/AiAgentDashboardTest.php` (the dashboard page's Livewire behavior) — and the `GenerationApplier` extraction it required is covered by the full pre-existing `AiAssistantPanelTest.php`/`AiContentAssistantTest.php` suites passing unchanged. None of the following are tested at all today: `Article::scopePublished()` time-based logic, `BlogController` (any of the public methods, either locale), `SeoController` sitemap/RSS XML correctness, `PreviewController`'s signed-URL gate, `PublishDueArticles`'s own core due-article flip (only its new `ContentPlan` sync side effect is tested), the Article Filament resource, or the `MediaLibrary`/`SeoCenter`/`InternalLinkingCenter`/`ContentPlanner`'s own Livewire interactions beyond what's listed above (folder CRUD, upload wiring, filters, CSV download, bulk suggestion approval, graph rendering) — the latter were verified manually in a browser during development (see git history) but have no automated coverage. Note: `ExampleTest` currently fails when run (it hits `/` against an unmigrated in-memory DB) — pre-existing, run new tests by file path.

Priority order for adding real tests (highest-value first):
1. `Article::scopePublished()` — feed it draft/scheduled-future/scheduled-due/published rows and assert visibility; this is the single most important piece of business logic in the app and the easiest to silently break.
2. `PublishDueArticles` — assert it flips due scheduled articles to published and leaves others untouched (its `ContentPlan`-sync side effect is already tested — see Section 25 — but the command's own original due-flip logic still is not).
3. `BlogController` feature tests for both locales — home/index/show return 200, show correct articles, 404 on missing slug.
4. `SeoController` — sitemap includes only published articles, RSS feed is well-formed XML and locale-filtered.
5. `PreviewController` — unsigned access is rejected, signed access works even for a draft article.

Run tests with `php artisan test` (or `composer test`, which clears config cache first). Use `laravel/pint` for style, not a separate linter. Do not adopt Pest unless the user asks — the project is on plain PHPUnit today (`phpunit/phpunit` ^12.5) with no Pest dependency installed.

## 32. Important Project Decisions

These are decisions already made — do not silently reverse them:
- **Pixel-parity with `ehsandibazar.com` is a deliberate, ongoing goal**, not an accident of a rushed build — a large fraction of commit history is dedicated to matching exact CSS values, image positioning, and layout from that reference site (see Brand Identity). When touching public-site visuals, check whether the current behavior was arrived at through this matching process before "fixing" it — it may be intentionally unusual to match the reference.
- **hreflang tags are intentionally disabled** (commented out in `master.blade.php`) pending the Turkish site being considered ready/complete. Do not re-enable them as a drive-by fix — that decision belongs to whoever is tracking TR-site completeness.
- **Bilingual content is two separate database rows**, not column-per-locale — see Multilingual Architecture. This has been the model since the `articles` table migration and should not be changed without a full data migration plan.
- **`SiteSetting` is a deliberately schemaless key/value CMS**, not a design placeholder awaiting "real" tables — this lets Ehsan edit homepage content from Filament without requiring a migration for every new content block.
- **No JS framework on the public site** — this is a stated architectural simplicity/performance choice (see Core Web Vitals), not a missing feature.
- **No analytics/tracking is installed today** — this is a verified current fact, not an oversight to silently "fix"; see Analytics & Tracking before adding any.
- **The production host has no SSH access.** This is a real, standing constraint on this project, not a temporary inconvenience — it's why the two unauthenticated maintenance routes existed in the first place (see Security Rules) and why `App\Filament\Pages\SystemMaintenance` exists now as their replacement. Any future deploy-ops tooling must assume no shell access to the server and work through either the Filament panel (preferred) or a properly authenticated/signed route.
- **There is no `Category`/`Tag` model or table** — `Article.category` is a plain free-text string column, and no tagging system has ever existed in this codebase. Internal Linking Center's Keyword Mapping (Section 22) was built as the explicitly-requested replacement for a taxonomy system, not a stopgap — don't add a real `Category`/`Tag` model system as a side effect of an unrelated task; that would be a separate, explicitly-scoped feature.
- **This app has no URL redirect mechanism** (no redirects table, no redirect middleware) — verified, not an oversight. Internal Linking Center's "Redirect Chains" category reports this honestly (always zero) rather than simulating a redirect system that doesn't exist. Building one is a legitimate future feature, but it's a genuinely separate piece of work from internal-linking suggestions — see Future Development Guidelines.
- **The AI Content Assistant (Section 23) is this project's first real LLM integration** — deliberately, not by oversight; everything in "AI Studio" before it was a paste-in import pipeline (see Architecture). Anthropic was picked as the first working provider, and the provider layer's intentional abstraction (`App\Services\AiAssistant\Contracts\AiProvider`) has since been exercised for real: four more vendors (OpenAI, Gemini, Grok, DeepSeek) and a full `ProviderManager` orchestration layer were added without touching `ContentAssistantService`'s calling code — see Section 24. Don't couple any calling code to a specific vendor's request/response shape; go through `ProviderManager`, never a concrete `AiProvider` implementation directly.
- **The AI Provider Integration Layer (Section 24) keeps the pre-existing `ANTHROPIC_API_KEY`/.env path as a permanent, load-bearing fallback, not a migration shim to be removed later** — this was an explicit, confirmed requirement (not a default assumption): any installation that has never touched the new Provider Settings screens must keep working exactly as it did before this feature shipped. Do not make database configuration mandatory, and do not remove the legacy binding in `AppServiceProvider`.
- **`seo_title`/`meta_description`/`og_title`/`og_description` are real columns now, not derived-only** — before this feature, the public templates always computed these from `title`/`excerpt`/`body`. They still do, but only as a fallback when the column is blank (`$article->seo_title ?: $article->title`, etc., in `blog-post.blade.php`/`page.blade.php` and their `tr/` counterparts). Don't remove the fallback — every existing article/page has these columns `null` and must keep rendering exactly as before until an admin (or the AI Assistant) sets one explicitly.
- **`Tag` (Section 25) is deliberately separate from `Keyword`, confirmed with the user, not a naming accident** — `Keyword` stays SEO-only (Internal Linking Center scoring); `Tag` is the new, general-purpose content-organization/filtering entity. Do not merge them into one model or table.
- **`ContentPlan` (Section 25) can exist with no `Article`/`Page` at all — this was an explicit, confirmed design decision ("Yes, standalone Idea"), not a temporary gap.** Materialization into a real record only ever happens via `moveToStage()` entering the `ai_draft` stage, and the created record's body always starts empty — content generation is never automatic, even for this new feature (see Section 23's already-established rule).
- **Only the seven `WorkflowStage::STAGE_*` slugs (`idea`/`research`/`ai_draft`/`human_review`/`seo_review`/`scheduled`/`published`/`archived`) have wired automatic behavior** (materialization, the `PublishDueArticles` sync, notifications, dashboard math) — admins can add/rename/reorder any other stage freely, but renaming or deleting one of these seven silently stops that specific integration point from firing. This is documented behavior, not a bug to "fix" by hardcoding stage IDs elsewhere.
- **`EditorialCalendar` is superseded by `ContentPlanner`'s Calendar view (Section 25), by explicit user request** — its URL and drag-and-drop scheduling behavior were kept working via a thin redirect, but it is not a second, independent calendar. Do not resurrect standalone calendar logic in `EditorialCalendar` itself.
- **`fa` (Persian) support in Brand Memory (Section 26) is a confirmed, deliberately narrow scope decision** — "Brand Memory values only," not a first step toward a third site content locale. Do not add `/fa` routes, `Article`/`Page` Persian support, or a `translate` target of `fa` as a side effect of this feature; that is a separate, much larger, explicitly-scoped decision the user has not made.
- **`BrandMemorySection` is deliberately separate from `AiProfile`, confirmed by design, not a naming collision** — `AiProfile` fills blank *import-time* fields on a manually-pasted article (language/status/category/author), it is opt-in per import and never touches a live AI prompt; `BrandMemory` is read automatically by every live generation call. Do not merge them or let one page manage the other's data.
- **Building a newsletter/social-media AI-generation feature was explicitly out of scope for Brand Memory (Section 26)** — `newsletter_tone`/`social_media_tone` are reserved sections only, ready for whenever such a feature is built, by the user's own confirmed choice ("just reserve the section"). Do not add a newsletter/social AI-assist action as a side effect of an unrelated task; that is a separate, explicitly-scoped feature.
- **`KnowledgeEntry` (Section 27) is deliberately separate from `BrandMemorySection`/`BrandMemoryValue`, not a duplicate feature** — Brand Memory is one fixed block of brand-identity knowledge always appended in full; Knowledge Base is a growing, filterable library of individual facts that only some of are relevant to any one generation, retrieved on demand. Do not merge the two models or their Filament screens.
- **Knowledge Base retrieval ("semantic retrieval") is AI-ranking over a keyword pre-filtered shortlist via the existing `ProviderManager` — not a vector database, and no vector database should be added for it** — this was a deliberate reuse of already-configured infrastructure, consistent with this project's dependency-light stance (see Coding Standards). If retrieval quality ever becomes a real, measured problem at a much larger content volume, that would be a separate, explicitly-scoped decision, not a default upgrade path.
- **`KnowledgeEntry.locale` is restricted to `en`/`tr`, deliberately narrower than Brand Memory's `en`/`tr`/`fa`** — Knowledge Base content is retrieved directly into generated site content (only ever en/tr), unlike Brand Memory's `fa` carve-out, which is reference-only and never retrieval-scored. Do not widen Knowledge Base's locale set to include `fa` as a side effect of an unrelated task.
- **One Click Publish (Section 28) deliberately does not persist Schema, Image Caption, Call To Action, Twitter Card, or Featured Image Prompt anywhere — confirmed with the user, not an oversight.** These are detected and reported in the mapping/preview for reference only. Do not add new `articles` columns or a new table for any of these as a side effect of an unrelated task; if one is ever wanted, it needs the same explicit sign-off any other schema change does.
- **`newsletter_summary` was explicitly ruled out for One Click Publish, by the user's own "stop it completely, don't continue" instruction** — it is not in `ArticleImportService::ALIASES` and has no special handling anywhere in the importer; a pasted response containing it is treated exactly like any other unrecognized key. Do not add newsletter-summary parsing/mapping/storage to the importer as a side effect of an unrelated task.
- **One Click Publish's `import()` deliberately does not auto-dispatch `GenerateInternalLinkSuggestions` after creating AI-declared internal-link suggestions** — `SuggestionEngine::generateAndPersist()` deletes any `pending` suggestion (regardless of `origin`) that its own rule-based rescoring doesn't independently reconfirm, so dispatching it in the same request would race against, and could delete, the `origin=ai` rows the import just created. This was discovered via a failing test during development. Rule-based regeneration for newly imported content still only happens through Internal Linking Center's existing manual button.

## 33. Things That Must Never Be Changed

- Do not change the `articles` table's two-row-per-translation model (`locale` + `translation_of`) without an explicit request and a data migration plan — every controller, scope, and Filament form assumes this shape.
- Do not remove the `scopePublished()` time-based fallback for `scheduled` articles — it is a deliberate resilience mechanism against scheduler downtime, not redundant logic.
- Do not hardcode `trainwithehsan.com` (or any other absolute domain) into PHP/Blade in place of `url()`/`url()->current()`/`APP_URL` — every URL-building call in the app already derives from request/config context; hardcoding breaks staging and local dev silently.
- Do not delete or bypass the Spatie activity log hooks on `Article` — Ehsan relies on the `ActivityLogPage` to see what changed and when.
- Do not add authentication/registration for public site visitors — this is a single-admin CMS by design; `/admin` is the only login surface.
- Do not re-enable hreflang tags as an incidental change (see Important Project Decisions).
- Do not introduce a JS framework, a component library, or a CSS framework migration (e.g. swapping the hand-rolled CSS for a full Tailwind rewrite) as a side effect of an unrelated task — these are significant architectural changes that need to be explicitly requested (see Brand Identity: never redesign UI without explicit approval).
- Do not re-add a public, unauthenticated route that runs Artisan commands (`migrate`, `cache:clear`, or anything else) — this was the exact defect `SystemMaintenance` (see Security Rules) was built to remove. Deploy-ops actions belong behind Filament's admin auth (extend `SystemMaintenance`) or, if they must be a plain route, behind `auth`/`signed` middleware.
- Do not remove or modify any analytics/tracking snippet without explicit approval, once one exists (see Analytics & Tracking).
- Do not reduce keyboard access, focus visibility, reduced-motion support, or semantic HTML/ARIA while making a visual change (see Accessibility Rules).
- Do not make `SuggestionEngine::generateAndPersist()` (or any future regeneration path) touch `approved`/`dismissed` rows in `internal_link_suggestions` — those represent a human decision and must survive regeneration untouched (see Section 22). Do not change link-suggestion approval to rewrite/inject into the *middle* of an `Article`/`Page` body — it must stay an append-only, marker-tagged addition; mid-content HTML injection risks corrupting an admin's existing RichEditor formatting with no reliable way to detect a "safe" insertion point.
- Do not make `App\Services\AiAssistant\ContentAssistantService::generate()`/`buildTranslationPayload()`/`classifyIntent()` (or the `RunAiContentGeneration`/`TranslateArticleDraft`/`ProcessAiChatMessage` jobs) write to a record directly — generation must always return a result for preview; only `AiAssistantPanel::applyGeneration()`/`::restoreGeneration()`/`::applyInternalLinkSuggestions()`, triggered by an explicit admin click, and the jobs that create genuinely new records (`TranslateArticleDraft`) may write (see Section 23). Do not couple `ContentAssistantService`, any of the three jobs, the Livewire component, or the Filament page to a specific vendor (`AnthropicProvider` or any other) — go through `App\Services\AiAssistant\ProviderManager`, never a concrete `AiProvider` implementation directly (see Section 24).
- Do not let AI-generated internal-link suggestions bypass `InternalLinkingCenter`'s existing approve/insert lifecycle — they must land as `pending`/`origin=ai` rows in `internal_link_suggestions` and go through the same append-only `insertLinkForSuggestion()`, never a second insertion path (see Section 23).
- Do not let `App\Jobs\TranslateArticleDraft` let the AI decide non-content metadata (locale, `translation_of`, publish status) — those are always assembled in code from the source record, never taken from the AI's translation response; the AI only ever translates content fields (see Section 23).
- Do not remove the two cancellation checkpoints in `RunAiContentGeneration`/`TranslateArticleDraft` (before starting, and a `fresh()` re-read right before writing the final result) — without them, a cancelled generation's result can still silently land after the fact (see Section 23).
- Do not remove the legacy `AnthropicProvider`/`NullProvider` container binding in `AppServiceProvider`, and do not make database-backed provider configuration mandatory anywhere in `ProviderManager::resolveCandidates()` — the `.env`-only fallback (`ANTHROPIC_API_KEY`) is a confirmed, permanent requirement so every pre-existing installation keeps working untouched (see Section 24 and Important Project Decisions).
- Do not store, log, or re-display a decrypted AI provider API key anywhere outside `ProviderManager`'s own use of it to make the request — never in `ai_usage_logs.error_message` (see `ProviderManager::sanitizeError()`), never pre-filled back into the `AiProviderConfigForm` edit field, never in an exception message that reaches the browser or a log file (see Section 24, Security Rules).
- Do not let `AiActionOverride`/`ai_provider_settings` reference a provider that isn't `is_usable`; `ProviderManager::resolveCandidates()`'s fallback-to-default behavior when an override target is unusable (see Section 24) must stay — a misconfigured per-field override must never hard-fail a generation when a usable default exists.
- Do not make `ContentPlan::materializeContent()` (Section 25) generate real body content, or call it from anywhere other than `moveToStage()` entering `ai_draft` — materialization must stay a plain, empty-body Eloquent create; the AI Assistant (or the admin) fills the body afterward, inside the normal editor, never automatically.
- Do not make `SuggestionEngine`/Internal Linking Center's `approved`/`dismissed` preservation rule (see above) the only place this pattern applies — `ContentPlanStageTransition` rows (Section 25) are similarly append-only history and must never be edited or deleted by a regeneration/sync path; `PublishDueArticles`' `ContentPlan` sync only ever calls `moveToStage()` (which appends), never touches past transition rows directly.
- Do not remove the `EditorialCalendar` redirect or let its route 404 — the URL must keep working per the confirmed "keep URLs... compatible where possible" requirement (see Section 25 and Important Project Decisions).
- Do not call `->databaseNotifications()` in `AdminPanelProvider` unconditionally — keep it guarded behind `Schema::hasTable('notifications')` (see Section 25). This bell renders on every panel page, including `SystemMaintenance`; on this project's no-SSH host, code and migrations deploy separately, so an unguarded call 500s the entire admin panel — including the one page that could otherwise run the pending migration — the moment this feature's code ships ahead of its migrations. This exact regression happened once already; do not reintroduce it.
- Do not add a second place that composes Brand Memory into a prompt — `App\Services\BrandMemory\BrandMemoryService::buildContext()` is the only composition logic, and `ContentAssistantService`'s shared `withBrandMemory()` helper is the only injection point (all three system-prompt builders call it). Do not duplicate the "grouped sections, English fallback, empty-string-when-nothing-configured" logic anywhere else (see Section 26).
- Do not delete a `BrandMemorySection` where `is_system = true` — the delete guard is enforced both in the Filament UI (the Select only lists non-system sections) and inside `BrandMemory::getHeaderActions()`'s `deleteSection` action body itself (`->where('is_system', false)`) — keep both; do not rely on the UI-level filter alone (see Section 26).
- Do not write to `BrandMemoryValue.content` via the query builder (e.g. `BrandMemoryValue::whereKey(...)->update(...)`) — always go through the Eloquent model instance so `LogsActivity` fires and version history stays complete, including for restores (`restoreVersion()` itself must remain a logged, undoable write, not a silent overwrite) (see Section 26).
- Do not build a live newsletter/social-media AI-generation feature as a side effect of touching Brand Memory's reserved `newsletter_tone`/`social_media_tone` sections — that was explicitly deferred (see Important Project Decisions).
- Do not make `ContentAssistantService::classifyIntent()` or `buildTranslationPayload()`/`buildTranslateSystemPrompt()` pull from the Knowledge Base — retrieval only happens inside `generate()` (see Section 27); chat-intent classification doesn't need supporting facts, and translation must preserve only the source content's own facts, not have new candidates mixed in from a fresh retrieval pass.
- Do not let a failed or unconfigured Knowledge Base retrieval ever block, delay, or degrade content generation — `KnowledgeBaseService::retrieveRelevant()`'s fallback to keyword-only ranking (see Section 27) must stay; do not make the AI-ranking step a hard dependency.
- Do not let `KnowledgeEntryAttachment` uploads go through `App\Services\Media\MediaProcessor` — that pipeline is image-only (WebP/thumbnail/responsive generation); Knowledge Base attachments are plain documents stored as-is (see Section 27 and Image Optimization Rules).
- Do not remove the explicit `'ai_gen_knowledge_entry_unique'` name from either the `ai_generation_knowledge_entry` table's unique index or the `2026_07_17_000000` fix-up migration that adds it on already-affected installs — the auto-generated name for this table+column pair exceeds MySQL's 64-character identifier limit (SQLSTATE 42000/1059), a real production failure this fix-up migration exists to correct (see Section 27).
- Do not dispatch `App\Jobs\GenerateInternalLinkSuggestions` from `ArticleImportService::import()` (or any other path that just wrote `origin=ai` `internal_link_suggestions` rows in the same request) — `SuggestionEngine::generateAndPersist()` deletes any `pending` suggestion its rule-based rescoring doesn't reconfirm, regardless of `origin`, so this would delete the AI-declared rows before an admin ever sees them (see Section 28).
- Do not add storage (a new column, a new table, or repurposing an existing field) for Schema, Image Caption, Call To Action, Twitter Card, or Featured Image Prompt in the One Click Publish importer — these are confirmed detect-and-report-only (see Section 28 and Important Project Decisions); adding storage for any of them is a separate, explicitly-scoped decision.
- Do not add `newsletter_summary` parsing, mapping, or storage to `ArticleImportService` — this was an explicit "stop it completely, don't continue" instruction from the user (see Important Project Decisions); a pasted response containing it must keep falling through to the ordinary "unknown field" warning, nothing more.
- Do not make `ArticleImportService::normalizeAndValidate()`'s `$overrides` parameter fill blanks instead of unconditionally overriding non-empty content — that is `$defaults`' job (profile defaults). `$overrides` (manual corrections, Section 28) must keep winning even over content the pasted input already provided; the two parameters are not interchangeable.
- Do not add a second write path for an `AiGeneration`'s result — `App\Services\AiAssistant\GenerationApplier::apply()`/`::restore()`/`::applyInternalLinkSuggestions()` (Section 29) is the only place that writes, called by both `AiAssistantPanel` and `App\Services\AiAgent\AgentFixService::approveFix()`. Do not inline a second copy of this logic into the AI Agent dashboard or any future caller — extend `GenerationApplier` instead.
- Do not make `AgentAuditService::generateAndPersist()` touch `ai_recommendations` rows with `status` `applied`/`rejected` — an admin's decision on a recommendation must survive every future audit run untouched, the same invariant `SuggestionEngine::generateAndPersist()` already established for `internal_link_suggestions` (see Section 22 and this same section above) and now applies a second time here (see Section 29).
- Do not give a `duplicate_topics`/`content_cannibalization`/`broken_links`/`missing_schema`/`orphan_pages`/`image_optimization` recommendation a `fix_type` — these are confirmed review-only, by design, because each needs an editorial or technical decision (merge two articles, pick the right href, wire a template, resize an image) that this project has already decided elsewhere is out of scope for unattended AI action (see Section 29 and the corresponding entries already in this list for redirects/schema/image processing). Adding automatic fixes for any of these is a separate, explicitly-scoped decision.
- Do not remove the `''`/`0` defaults on `ai_recommendations`' uniqueness columns (`content_type`/`content_id`/`related_content_type`/`related_content_id`/`locale`) in favor of `NULL` — MySQL/SQLite treat every `NULL` as distinct within a unique index, so `NULL`s there would silently break `AgentAuditService::persist()`'s `upsert()` for any finding without a specific record (see Section 29).
- Do not remove the explicit `'ai_recommendation_unique'` name from the `ai_recommendations` migration — the auto-generated name for this many-column unique index exceeds MySQL's 64-character identifier limit, the same lesson already documented for `ai_generation_knowledge_entry`/`taggables`/`notifications` above, applied proactively this time (see Section 29).

## 34. Future Development Guidelines

Ordered roughly by impact:
1. **Confirm/establish a real SQLite backup mechanism on the production server** — see SQLite Backup Strategy; this is currently unverified/likely missing and is a data-loss risk.
2. **Stand up minimal CI** — a GitHub Actions workflow running `pint --test` and `php artisan test` on every push/PR costs little and catches regressions the current zero-test setup cannot.
3. **Write the priority-1-through-5 tests listed in Testing Strategy**, starting with `Article::scopePublished()`.
4. **Add JSON-LD structured data to the blog index and standalone `Page` records** — `Article` (blog posts), `Person` (about page), and `Organization`+`Person` (home page) schema are already implemented; the blog index (`blog.blade.php`/`tr/blog.blade.php`) and every `Page` (`page.blade.php`/`tr/page.blade.php`) are the remaining gaps, both flagged live under "Missing Schema" in the SEO Center (see Section 8).
5. **Wire up `og:image` for blog posts** using the article's featured image — the `@yield('og_image')` mechanism already exists (used by the About page's `seo_og_image` field) and just needs the same `@section('og_image', ...)` call added to `blog-post.blade.php`/`tr/blog-post.blade.php`.
6. **Wire responsive/WebP delivery into the public templates.** The Media Library pipeline (see Image Optimization Rules) already generates WebP + responsive variants for every article/page featured image — `blog-post.blade.php`/`tr/blog-post.blade.php` etc. still render the plain original via CSS `background-image`. Swapping in `Media::webp_url`/`responsive_urls` is a real, explicitly-scoped markup decision (background-image doesn't support `srcset`), not a drop-in — get sign-off first per Brand Identity.
7. **Extend DAM management to the remaining `FileUpload` fields** (`HomepageSettings`, `AboutPageSettings`, `MenuSettings`, `FooterSettings`) the same way `ArticleForm`/`PageForm` were wired, so their images get WebP/thumbnail/responsive variants and real usage tracking too — deliberately left out of the initial Media Library build to keep that change's blast radius contained to the blog/pages system.
8. **Revisit the EN/TR duplication** once the Turkish site is considered complete and stable — evaluate whether a shared-view + locale-parameter approach is worth the refactor cost at that point (not before; premature to do while TR content/design is still catching up to EN, per the hreflang decision above). This is exactly the kind of large refactor the Development Principles above say to avoid without measurable benefit and explicit sign-off.
9. **Update `README.md`** to describe this project specifically (currently the unmodified Laravel skeleton README) and remove the unused `resources/views/welcome.blade.php`.
10. **Resolve the font inconsistency** (Vite prefetches "Instrument Sans" via Bunny Fonts; the site actually renders "Poppins" from Google Fonts) — either wire up the configured font or remove the unused prefetch, and consider self-hosting the chosen font for Core Web Vitals.
11. Set `APP_NAME` in `.env.example` (and real env) to something other than the default `Laravel`.
12. **Decide on and implement an analytics stack** (GA4, Clarity, Meta Pixel, or otherwise) once the business is ready to track visitor behavior — currently deliberately undocumented because nothing is installed (see Analytics & Tracking).
13. **Build a real URL redirect system** (redirects table + middleware) if slug changes become common enough to matter — today a changed slug just produces a "Broken Internal Link" in both SEO Center and Internal Linking Center (see Section 22's "Redirect Chains" note). This is a genuinely separate feature from internal-linking suggestions; don't fold it into that service.
14. **Consider a real force-directed graph layout** for Internal Linking Center if content volume grows enough that the current deterministic circular layout (Section 22) becomes hard to read — only worth the added complexity (and possibly a small client-side library) once there's an actual readability problem, not preemptively.
15. ~~Add a second `AiProvider` implementation~~ — **done** (Section 24): OpenAI, Gemini, Grok, and DeepSeek were all added alongside a `ProviderManager` orchestration layer, per-field routing, failover, encrypted credentials, usage logging, and cost estimation. A future 6th/7th vendor follows the exact same one-class-plus-one-`DRIVERS`-entry pattern documented there — not a refactor.
16. **Wire AI-suggested schema (Section 23's `schema` field) into the actual templates** if the read-only JSON-snippet preview proves not enough — today it's deliberately copy-paste-to-a-developer only, consistent with `SeoAuditService::missingSchema()`'s existing "template-level gap, needs explicit sign-off" stance; don't wire it in as a side effect of an unrelated task.
17. **Consider a lightweight per-provider spend/quota alert** on top of `ai_usage_logs` (Section 24) — e.g. a daily digest or a Filament notification once estimated cost crosses an admin-configured threshold — genuinely useful once real usage volume exists, but speculative today since no provider has been used in production yet; don't build it preemptively.
18. **Real multi-user access is still schema-ready only, not implemented.** The Editorial Workflow (Section 25) added nullable `author_id`/`assigned_to` FKs on `ContentPlan` and a per-user `NotificationPreference` table specifically so a future multi-user team fits without another migration — but `User::canAccessPanel()` still hardcodes a single admin email, and no roles/permissions system exists. Wiring up real RBAC (who can move a card to `published`, who can see whose tasks, etc.) is a separate, explicitly-scoped security decision — don't infer it from the presence of these columns.
19. **Enable additional notification channels (mail/Slack/Telegram/push) once needed** — the four `App\Notifications\*` classes (Section 25) were built channel-agnostic on purpose (`via()` filtered through `NotificationPreference`); adding a channel is purely additive (one `toMail()`/`toSlack()` method + extending `AVAILABLE_CHANNELS`), no call-site changes required.
20. **Build a real newsletter/social-media AI-assist feature** on top of Brand Memory's reserved `newsletter_tone`/`social_media_tone` sections (Section 26) once wanted — a new `ActionRegistry` field plus a compose-screen AI-assist button would automatically inherit the full Brand Memory context through the same `ContentAssistantService` path every other field already uses; no new composition logic needed, just a new caller.
21. **`fa` (Persian) as a full third site content locale** is a legitimate future feature but a much larger, separate decision than Brand Memory's value-only Persian support (Section 26) — would touch routes, `Article`/`Page` locale columns, the `translate` target list, and every EN/TR-duplicated view. Revisit only alongside (or after) the EN/TR duplication question in item 8 above, with explicit sign-off — don't infer it from `BrandMemory::LOCALES` already listing `fa`.
22. **Knowledge Base attachment content isn't parsed into the AI prompt yet** (Section 27) — an uploaded PDF/document is stored for admin/AI *reference* only; the AI never reads its contents, only the entry's own `content` text field. Extracting text from attachments (and feeding it into retrieval/generation) is a legitimate future enhancement, but a genuinely separate piece of work (PDF/document parsing) from what shipped here — don't build it as a side effect of an unrelated task.
23. **A dedicated "Knowledge used" filter/report on top of `ai_generation_knowledge_entry`** (Section 27) — e.g. "which entries are never used" or "which generations relied most on Knowledge Base facts" — would be a natural read-only dashboard addition once real usage volume exists, mirroring `AiUsageLogResource`'s read-only shape (Section 24). Speculative today; don't build it preemptively.
24. **Wire Schema/Twitter Card/CTA/Image Caption into the actual templates** (Section 28) if the current detect-and-report-only posture ever proves not enough — this is the same "template-level gap, needs explicit sign-off" stance already documented for AI-suggested schema (item 16 above); don't wire any of them in as a side effect of an unrelated task.
25. **A real newsletter/social-media summary feature for One Click Publish** — `newsletter_summary` was explicitly ruled out for this round (see Important Project Decisions); if wanted later, it's a separate, explicitly-scoped feature, potentially built on Brand Memory's already-reserved `newsletter_tone` section (item 20 above) the same way any other new `ActionRegistry` field would be.
26. **Rule-based internal-link regeneration scoped to a single article** — `GenerateInternalLinkSuggestions`/`SuggestionEngine::generateAndPersist()` today always recompute site-wide; a narrower "just this article" mode would make it safe to auto-trigger right after a One Click Publish import (Section 28) without risking the delete-race documented there. Worth building only if the current manual-button workflow proves too slow at real content volume — not preemptively.
27. **A dedicated "AI Agent recommendation" notification** (reusing the channel-agnostic `App\Notifications\*` infrastructure from Section 25) once a weekly `agent:audit` run finds a meaningful number of new recommendations — today the dashboard is pull-only (an admin has to open it to see what changed since the last audit). Natural, but genuinely separate from the detection engine itself; don't build it as a side effect of an unrelated AI Agent change.
28. **Per-provider/per-category routing for AI Agent fixes** (reusing `AiActionOverride`, Section 24) — today a fix always goes through whichever provider `ProviderManager::resolveCandidates()` already resolves for that `ActionRegistry` field, exactly like every other caller; a dedicated override just for Agent-originated generations was not requested and would need its own `actionKey` convention. Speculative today.

When in doubt about whether a change fits this project's grain, re-read sections 2, 14, 32, and 33 above before proceeding.

## 35. Keeping CLAUDE.md Updated

This file is the **single source of truth** for future Claude Code sessions working on this repository — it exists so a new session can be productive immediately without re-exploring the entire codebase from scratch.

- Whenever a change affects architecture, conventions, deployment, SEO, the CMS/Filament setup, workflows (git/staging/deployment/publishing), or any of the "Important Project Decisions"/"Things That Must Never Be Changed" — **update the relevant section of this file in the same commit as the code change**, whenever appropriate to do so.
- If this file and the actual codebase ever disagree, that is a bug in this file — fix the documentation, don't let it quietly drift out of date.
- Prefer editing the existing section that already covers a topic over bolting on a new one; keep the numbering contiguous when sections are added or removed, and update any cross-references (e.g. "see Section N") that shift as a result.
