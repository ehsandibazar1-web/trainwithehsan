<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Filament\Forms\Components\MediaPickerInput;
use App\Filament\RichContent\MediaLibraryRichContentPlugin;
use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ArticleForm
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
                    ->helperText('The last part of the article URL — letters, numbers and dashes only. Auto-filled from the title.'),

                Select::make('translation_of')
                    ->label('Translation of')
                    ->options(fn () => Article::query()->pluck('title', 'id'))
                    ->searchable()
                    ->nullable()
                    ->helperText('Optional — if this article is the other-language version of an existing article, pick it here.'),

                TextInput::make('category')
                    ->label('Category')
                    ->nullable(),

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

                Textarea::make('excerpt')
                    ->label('Excerpt')
                    ->rows(3)
                    ->nullable()
                    ->helperText('Short text shown on the article card in the blog list.'),

                Section::make('SEO & social preview (optional)')
                    ->description('Leave blank to keep using the title/excerpt automatically — only fill these in if you want different wording for Google or social shares. The AI Assistant (button at the top of this page once saved) can suggest all four.')
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
                    ->helperText('The search phrases this article should rank for. Used by the Internal Linking Center to suggest which other articles/pages should link here.')
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
                    ->label('Article body')
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('articles/inline')
                    ->plugins([MediaLibraryRichContentPlugin::make('articles/inline')])
                    ->columnSpanFull(),

                Repeater::make('faqs')
                    ->label('Frequently Asked Questions (optional)')
                    ->helperText('Add question-and-answer pairs shown at the bottom of the article. Leave empty to hide the FAQ section. These also help this article appear in Google as a rich FAQ result. Each language is edited separately, on that language\'s article.')
                    ->schema([
                        TextInput::make('question')
                            ->label('Question')
                            ->required(),
                        Textarea::make('answer')
                            ->label('Answer')
                            ->rows(3)
                            ->required(),
                    ])
                    ->addActionLabel('Add question')
                    ->reorderable()
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? null)
                    ->defaultItems(0)
                    ->nullable()
                    ->columnSpanFull(),

                // پنجره‌ی انتخابِ رسانه‌ی یکپارچه — همان پیکری که (به‌تدریج) همه‌جای CMS باز می‌شود.
                // مقدار همان رشته‌ی disk_path است که FileUpload قبلی ذخیره می‌کرد، پس هر خواننده‌ی
                // موجود و هر مقاله‌ی موجود بدونِ تغییر کار می‌کند. آپلودِ تازه، انتخاب از کتابخانه، و
                // ویرایشِ ALT/کپشن همه درونِ خودِ پنجره انجام می‌شوند (نگاه کنید به App\Livewire\MediaPicker).
                MediaPickerInput::make('image_path')
                    ->label('Featured image')
                    ->helperText('Pick from the Media Library or upload a new one — WebP, thumbnail and responsive sizes are generated automatically.')
                    ->onlyImages()
                    ->uploadDirectory('articles')
                    ->nullable(),

                TextInput::make('image_alt')
                    ->label('Featured image — ALT text')
                    ->helperText('Describes the hero image for Google Images and screen readers. Each language has its own — write it in this article\'s language (e.g. English here, Turkish on the Turkish article). Leave blank to fall back to the article title.')
                    ->maxLength(255),

                Section::make('AI Image Prompts (optional)')
                    ->description('Editable prompts used by the AI Image Pipeline. Leave "Hero image" blank to let the AI Assistant build one automatically from the title/category/excerpt when you click "Generate Hero Image" in the sidebar. The other three are stored for future use (thumbnail/social/OG image generation) and are not used yet.')
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

                TextInput::make('author_name')
                    ->label('Author')
                    ->default('Ehsan Dibazar')
                    ->required(),

                TextInput::make('reading_time')
                    ->label('Reading time (minutes)')
                    ->numeric()
                    ->nullable(),

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
                    ->helperText('For "Scheduled": set a future date/time — the article goes live automatically at that moment.'),
            ]);
    }
}
