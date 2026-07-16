<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\AiProfile;
use App\Models\AiTemplate;
use App\Models\ImportLog;
use App\Services\ArticleImport\ArticleImportService;
use App\Services\Seo\SeoAuditService;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use UnitEnum;

class AiImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'AI Import';

    protected static ?string $title = 'One Click Publish';

    protected string $view = 'filament.pages.ai-import';

    public ?array $data = [];

    /** نتیجه‌ی آخرین Validate/Preview/Import — برای نمایش در ویو */
    public ?array $analysis = null;

    public ?array $preview = null;

    public ?array $importedInfo = null;

    public function mount(): void
    {
        $this->form->fill(['format' => 'auto', 'raw' => '', 'template_id' => null, 'profile_id' => null, 'corrections' => []]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('template_id')
                    ->label('Load a saved template (optional)')
                    ->options(fn () => AiTemplate::orderBy('name')->pluck('name', 'id'))
                    ->placeholder('— start from a blank paste area —')
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state && ($template = AiTemplate::find($state))) {
                            $set('raw', $template->content);
                            $set('format', $template->format);
                        }
                    })
                    ->helperText('Templates are managed under AI Studio → AI Templates.'),

                Select::make('profile_id')
                    ->label('AI profile (optional)')
                    ->options(fn () => AiProfile::orderBy('name')->pluck('name', 'id'))
                    ->placeholder('— no profile —')
                    ->helperText('Tags the import with the provider and fills in defaults (language, status, …) the pasted content leaves out. Managed under AI Studio → AI Profiles.'),

                Select::make('format')
                    ->label('Content format')
                    ->options([
                        'auto' => 'Detect automatically',
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                        'html' => 'HTML',
                        'xml' => 'XML',
                        'custom' => 'Custom [[FIELD]] markers',
                    ])
                    ->default('auto')
                    ->selectablePlaceholder(false),

                Textarea::make('raw')
                    ->label('Paste the AI-generated article here')
                    ->rows(16)
                    ->helperText('Paste the AI output exactly as produced — JSON, Markdown, HTML, XML, or the custom [[FIELD]] format — then press Validate or Preview. Nothing is saved until you press Import.'),

                Section::make('Manual corrections (optional)')
                    ->description('Auto-filled from the last Preview — edit anything below before importing. These always win over the pasted content, even if a value was already provided there.')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('corrections.title')->label('Title'),
                        TextInput::make('corrections.slug')->label('Slug'),
                        TextInput::make('corrections.category')->label('Category'),
                        Select::make('corrections.status')
                            ->label('Publish status')
                            ->options(['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'])
                            ->placeholder('— keep as parsed —'),
                        DateTimePicker::make('corrections.published_at')->label('Publish date'),
                        TextInput::make('corrections.author_name')->label('Author'),
                        Textarea::make('corrections.excerpt')->label('Excerpt')->rows(2)->columnSpanFull(),
                        TextInput::make('corrections.seo_title')->label('SEO title'),
                        TextInput::make('corrections.og_title')->label('Open Graph title'),
                        Textarea::make('corrections.meta_description')->label('Meta description')->rows(2),
                        Textarea::make('corrections.og_description')->label('Open Graph description')->rows(2),
                        TextInput::make('corrections.tags')->label('Tags')->helperText('Comma-separated.')->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    /** پروفایل انتخاب‌شده — پیش‌فرض‌ها و نام ارائه‌دهنده از اینجا می‌آیند */
    private function selectedProfile(): ?AiProfile
    {
        $id = $this->form->getState()['profile_id'] ?? null;

        return $id ? AiProfile::find($id) : null;
    }

    public function runValidate(): void
    {
        $this->importedInfo = null;
        $this->preview = null;
        $this->analysis = $this->analyzeInput();

        if ($this->analysis['errors'] === []) {
            Notification::make()->success()->title('Everything looks good — ready to import')->send();
        } else {
            Notification::make()->danger()->title('Please fix the problems listed below')->send();
        }
    }

    public function runPreview(): void
    {
        $this->importedInfo = null;

        // پیش‌نمایش در تاریخچه ثبت می‌شود (Preview History) — ولی همچنان هیچ مقاله‌ای ساخته نمی‌شود
        $state = $this->form->getState();
        $profile = $this->selectedProfile();

        $this->analysis = app(ArticleImportService::class)->preview(
            (string) ($state['raw'] ?? ''),
            (string) ($state['format'] ?? 'auto'),
            ['user_id' => auth()->id(), 'source' => 'panel', 'ai_provider' => $profile?->provider],
            $profile?->importDefaults() ?? [],
            $this->correctionOverrides($state),
        );
        $this->preview = $this->analysis['errors'] === [] ? $this->buildPreview($this->analysis['payload']) : null;

        if ($this->preview !== null) {
            // فیلدهای «اصلاح دستی» با مقادیرِ همین پیش‌نمایش پر می‌شوند تا ادمین فقط چیزی را که
            // می‌خواهد عوض کند، نه اینکه از صفر تایپ کند
            $this->loadCorrectionsFromPayload($this->analysis['payload']);
        } else {
            Notification::make()->danger()->title('Fix the problems below to see the preview')->send();
        }
    }

    public function runImport(): void
    {
        $state = $this->form->getState();
        $profile = $this->selectedProfile();

        $result = app(ArticleImportService::class)->import(
            (string) ($state['raw'] ?? ''),
            (string) ($state['format'] ?? 'auto'),
            ['user_id' => auth()->id(), 'source' => 'panel', 'ai_provider' => $profile?->provider],
            $profile?->importDefaults() ?? [],
            $this->correctionOverrides($state),
        );

        if ($result['article'] === null) {
            $this->analysis = $this->analyzeInput();
            $this->preview = null;
            $this->importedInfo = null;
            Notification::make()->danger()->title('Import failed — see the problems listed below')->send();

            return;
        }

        $article = $result['article'];
        $this->importedInfo = [
            'title' => $article->title,
            'status' => $article->status,
            'edit_url' => ArticleResource::getUrl('edit', ['record' => $article]),
            'public_url' => url($article->path()),
            'warnings' => $result['warnings'],
        ];
        $this->analysis = null;
        $this->preview = null;
        $this->form->fill([
            'format' => $state['format'] ?? 'auto',
            'raw' => '',
            'template_id' => null,
            'profile_id' => $state['profile_id'] ?? null,
            'corrections' => [],
        ]);

        Notification::make()->success()->title('Article imported: '.$article->title)->send();
    }

    private function analyzeInput(): array
    {
        $state = $this->form->getState();
        $profile = $this->selectedProfile();

        return app(ArticleImportService::class)->analyze(
            (string) ($state['raw'] ?? ''),
            (string) ($state['format'] ?? 'auto'),
            $profile?->importDefaults() ?? [],
            $this->correctionOverrides($state),
        );
    }

    /**
     * حالت فرمِ «اصلاح دستی» را به شکل $overrides تبدیل می‌کند که ArticleImportService می‌شناسد —
     * فقط فیلدهای واقعاً پرشده فرستاده می‌شوند تا فیلدهای خالی هرگز چیزی را جای‌گزین نکنند
     * (نگاه کنید به ArticleImportService::normalizeAndValidate()).
     */
    private function correctionOverrides(array $state): array
    {
        $c = $state['corrections'] ?? [];

        $overrides = array_filter([
            'title' => $c['title'] ?? null,
            'slug' => $c['slug'] ?? null,
            'category' => $c['category'] ?? null,
            'status' => $c['status'] ?? null,
            'published_at' => $c['published_at'] ?? null,
            'author_name' => $c['author_name'] ?? null,
            'excerpt' => $c['excerpt'] ?? null,
            'seo_title' => $c['seo_title'] ?? null,
            'og_title' => $c['og_title'] ?? null,
            'meta_description' => $c['meta_description'] ?? null,
            'og_description' => $c['og_description'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (! empty($c['tags'])) {
            $overrides['tags'] = array_values(array_filter(array_map('trim', explode(',', $c['tags']))));
        }

        return $overrides;
    }

    private function loadCorrectionsFromPayload(array $p): void
    {
        $this->form->fill(array_merge($this->form->getState(), [
            'corrections' => [
                'title' => $p['title'] ?? '',
                'slug' => $p['slug'] ?? '',
                'category' => $p['category'] ?? '',
                'status' => $p['status'] ?? null,
                'published_at' => $p['published_at']?->format('Y-m-d H:i'),
                'author_name' => $p['author_name'] ?? '',
                'excerpt' => $p['excerpt'] ?? '',
                'seo_title' => $p['seo_title'] ?? '',
                'og_title' => $p['og_title'] ?? '',
                'meta_description' => $p['meta_description'] ?? '',
                'og_description' => $p['og_description'] ?? '',
                'tags' => implode(', ', $p['tags'] ?? []),
            ],
        ]));
    }

    // بازگردانی یک ایمپورت موفق مستقیماً از جدول «واردات اخیر» — قبل از این، ArticleImportService::rollback()
    // در کد وجود داشت ولی به هیچ دکمه‌ای در پنل وصل نبود
    public function rollbackLog(int $logId): void
    {
        $log = ImportLog::find($logId);

        if (! $log) {
            return;
        }

        $result = app(ArticleImportService::class)->rollback($log, ['user_id' => auth()->id()]);

        Notification::make()
            ->color($result['ok'] ? 'success' : 'danger')
            ->title($result['ok'] ? 'Rolled back' : 'Could not roll back')
            ->body($result['message'])
            ->send();
    }

    /** ساخت داده‌ی پیش‌نمایش از payload نرمال‌شده — هیچ چیزی ذخیره نمی‌شود */
    private function buildPreview(array $p): array
    {
        return [
            'title' => $p['title'],
            'body' => $p['body'],
            'excerpt' => $p['excerpt'],
            'faqs' => $p['faqs'] ?? [],
            'image' => $p['featured_image']
                ? (str_starts_with($p['featured_image'], 'http') ? $p['featured_image'] : asset('storage/'.$p['featured_image']))
                : null,
            'image_alt' => $p['image_alt'] ?? null,
            'locale' => strtoupper($p['locale']),
            'category' => $p['category'],
            'tags' => $p['tags'] ?? [],
            'keywords' => $p['keywords'] ?? [],
            'status' => ucfirst($p['status']),
            'published_at' => $p['published_at']?->format('Y-m-d H:i'),
            'internal_links' => $p['internal_links'] ?? [],
            'external_links' => $this->verifiedExternalLinks($p['external_links'] ?? []),
            'seo' => [
                'page_title' => $p['seo_title'] ?: ($p['title'].' — Ehsan Dibazar'),
                'meta_description' => $p['meta_description'] ?: ($p['excerpt'] ?: Str::limit(trim(strip_tags($p['body'])), 150)),
                'og_title' => $p['og_title'] ?? null,
                'og_description' => $p['og_description'] ?? null,
                'canonical' => url(($p['locale'] === 'tr' ? '/tr/blog/' : '/blog/').$p['slug']),
            ],
        ];
    }

    // هر لینک خارجیِ پیشنهادیِ هوش مصنوعی پیش از نمایش، با همان الگوی SeoAuditService که
    // دستیار هوش مصنوعی (Section 23) هم استفاده می‌کند، بررسی می‌شود که واقعاً بالا باشد
    private function verifiedExternalLinks(array $links): array
    {
        if ($links === []) {
            return [];
        }

        $checked = app(SeoAuditService::class)->checkUrls(collect($links)->pluck('url')->all());

        return collect($links)->map(fn (array $link) => array_merge($link, [
            'broken' => $checked[$link['url']]['broken'] ?? false,
        ]))->all();
    }
}
