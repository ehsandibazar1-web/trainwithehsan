<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Filament\RichContent\MediaLibraryRichContentPlugin;
use App\Models\Page;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('locale')
                    ->label('Language / Dil')
                    ->options([
                        'en' => 'English',
                        'tr' => 'Türkçe',
                    ])
                    ->default('en')
                    ->required(),

                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, callable $set) {
                        $set('slug', Str::slug($state ?? ''));
                    }),

                TextInput::make('slug')
                    ->label('Slug (URL)')
                    ->required()
                    ->helperText('The page URL — e.g. "privacy-policy" makes the page available at /privacy-policy (English) or /tr/privacy-policy (Türkçe). Avoid the reserved words: blog, about, admin, feed, tr, preview.'),

                Select::make('translation_of')
                    ->label('Translation of')
                    ->options(fn () => Page::query()->pluck('title', 'id'))
                    ->searchable()
                    ->nullable()
                    ->helperText('Optional — if this page is the other-language version of an existing page, pick it here.'),

                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Tags')
                    ->helperText('Used for organizing and filtering content in the Content Planner — separate from the SEO keywords below.')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Tag name')
                            ->required(),
                    ]),

                Section::make('SEO & social preview (optional)')
                    ->description('Leave blank to keep using the title/body automatically — only fill these in if you want different wording for Google or social shares. The AI Assistant (button at the top of this page once saved) can suggest these.')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('seo_title')
                            ->label('SEO title')
                            ->maxLength(70)
                            ->nullable(),
                        TextInput::make('meta_description')
                            ->label('Meta description')
                            ->maxLength(160)
                            ->nullable(),
                        TextInput::make('meta_keywords')
                            ->label('Meta keywords (optional)')
                            ->helperText('Rarely used by search engines today, but some tools still read it. Comma-separated.')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('canonical_url')
                            ->label('Canonical URL (advanced)')
                            ->helperText('Leave blank to use this page\'s own URL (recommended for almost every page). Only set this if this content is a duplicate of another page and you want search engines to credit that page instead.')
                            ->url()
                            ->maxLength(255)
                            ->nullable(),
                        Select::make('robots')
                            ->label('Search engine indexing')
                            ->helperText('Leave as "Default" unless you specifically want to hide this page from Google (e.g. a thank-you page).')
                            ->options([
                                'index,follow' => 'Default — index this page and follow its links',
                                'noindex,follow' => 'Hide from search results, but still follow its links',
                                'noindex,nofollow' => 'Hide from search results and don\'t follow its links',
                                'index,nofollow' => 'Index this page but don\'t follow its links',
                            ])
                            ->native(false)
                            ->nullable(),
                        TextInput::make('og_title')
                            ->label('Social share title')
                            ->maxLength(70)
                            ->nullable(),
                        TextInput::make('og_description')
                            ->label('Social share description')
                            ->maxLength(160)
                            ->nullable(),
                    ]),

                Repeater::make('keywords')
                    ->relationship()
                    ->label('Target keywords (SEO)')
                    ->helperText('The search phrases this page should rank for. Used by the Internal Linking Center to suggest which articles/pages should link here.')
                    ->schema([
                        TextInput::make('keyword')
                            ->label('Keyword')
                            ->required(),
                    ])
                    ->addActionLabel('Add keyword')
                    ->itemLabel(fn (array $state): ?string => $state['keyword'] ?? null)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->columnSpanFull(),

                RichEditor::make('body')
                    ->label('Page content')
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('pages/inline')
                    ->plugins([MediaLibraryRichContentPlugin::make('pages/inline')])
                    ->columnSpanFull(),

                // پنجره‌ی انتخابِ رسانه‌ی یکپارچه — همان مقدارِ رشته‌ای disk_path که FileUpload قبلی
                // ذخیره می‌کرد؛ ALT درونِ خودِ پنجره ویرایش می‌شود (نگاه کنید به App\Livewire\MediaPicker).
                MediaPickerInput::make('image_path')
                    ->label('Featured image')
                    ->helperText('Pick from the Media Library or upload a new one — WebP, thumbnail and responsive sizes are generated automatically.')
                    ->onlyImages()
                    ->uploadDirectory('pages')
                    ->nullable(),

                TextInput::make('image_alt')
                    ->label('Featured image — ALT text')
                    ->helperText('Describes the hero image for Google Images and screen readers. Each language has its own — write it in this page\'s language. Leave blank to fall back to the page title.')
                    ->maxLength(255),

                Section::make('AI Image Prompts (optional)')
                    ->description('Editable prompts used by the AI Image Pipeline. Leave "Hero image" blank to let the AI Assistant build one automatically from the title/excerpt when you click "Generate Hero Image" in the sidebar. The other three are stored for future use (thumbnail/social/OG image generation) and are not used yet.')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('hero_image_prompt')
                            ->label('Hero image prompt')
                            ->rows(2)
                            ->nullable(),
                        Textarea::make('thumbnail_image_prompt')
                            ->label('Thumbnail image prompt')
                            ->rows(2)
                            ->nullable(),
                        Textarea::make('og_image_prompt')
                            ->label('Open Graph image prompt')
                            ->rows(2)
                            ->nullable(),
                        Textarea::make('social_image_prompt')
                            ->label('Social image prompt')
                            ->rows(2)
                            ->nullable(),
                    ]),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ])
                    ->default('draft')
                    ->required(),

                DateTimePicker::make('published_at')
                    ->label('Publish date')
                    ->nullable()
                    ->helperText('For "Scheduled": set a future date/time — the page goes live automatically at that moment.'),
            ]);
    }
}
