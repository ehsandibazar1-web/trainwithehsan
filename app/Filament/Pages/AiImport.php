<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Services\ArticleImport\ArticleImportService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class AiImport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'AI Import';

    protected static ?string $title = 'AI Import';

    protected string $view = 'filament.pages.ai-import';

    public ?array $data = [];

    /** نتیجه‌ی آخرین Validate/Preview/Import — برای نمایش در ویو */
    public ?array $analysis = null;

    public ?array $preview = null;

    public ?array $importedInfo = null;

    public function mount(): void
    {
        $this->form->fill(['format' => 'auto', 'raw' => '']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('format')
                    ->label('Content format')
                    ->options([
                        'auto' => 'Detect automatically',
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                    ])
                    ->default('auto')
                    ->selectablePlaceholder(false),

                Textarea::make('raw')
                    ->label('Paste the AI-generated article here')
                    ->rows(16)
                    ->helperText('Paste the JSON or Markdown exactly as the AI produced it, then press Validate or Preview. Nothing is saved until you press Import.'),
            ])
            ->statePath('data');
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
        $this->analysis = $this->analyzeInput();
        $this->preview = $this->analysis['errors'] === [] ? $this->buildPreview($this->analysis['payload']) : null;

        if ($this->preview === null) {
            Notification::make()->danger()->title('Fix the problems below to see the preview')->send();
        }
    }

    public function runImport(): void
    {
        $state = $this->form->getState();

        $result = app(ArticleImportService::class)->import(
            (string) ($state['raw'] ?? ''),
            (string) ($state['format'] ?? 'auto'),
            ['user_id' => auth()->id(), 'source' => 'panel'],
        );

        if ($result['article'] === null) {
            $this->analysis = app(ArticleImportService::class)->analyze((string) ($state['raw'] ?? ''), (string) ($state['format'] ?? 'auto'));
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
        $this->form->fill(['format' => $state['format'] ?? 'auto', 'raw' => '']);

        Notification::make()->success()->title('Article imported: '.$article->title)->send();
    }

    private function analyzeInput(): array
    {
        $state = $this->form->getState();

        return app(ArticleImportService::class)->analyze(
            (string) ($state['raw'] ?? ''),
            (string) ($state['format'] ?? 'auto'),
        );
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
            'locale' => strtoupper($p['locale']),
            'category' => $p['category'],
            'status' => ucfirst($p['status']),
            'published_at' => $p['published_at']?->format('Y-m-d H:i'),
            'seo' => [
                'page_title' => $p['title'].' — Ehsan Dibazar',
                'meta_description' => $p['excerpt'] ?: Str::limit(trim(strip_tags($p['body'])), 150),
                'canonical' => url(($p['locale'] === 'tr' ? '/tr/blog/' : '/blog/').$p['slug']),
            ],
        ];
    }
}
