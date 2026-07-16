<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use App\Services\Media\MediaProcessor;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

                Textarea::make('excerpt')
                    ->label('Excerpt')
                    ->rows(3)
                    ->nullable()
                    ->helperText('Short text shown on the article card in the blog list.'),

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

                FileUpload::make('image_path')
                    ->label('Featured image')
                    ->helperText('Automatically added to the Media Library (WebP + thumbnail + responsive sizes generated).')
                    ->image()
                    ->disk('public')
                    ->directory('articles')
                    // ثبت خودکار در کتابخانه‌ی رسانه (DAM) + تولید WebP/تامبنیل/سایزهای واکنش‌گرا در همان لحظه‌ی آپلود
                    ->saveUploadedFileUsing(fn (BaseFileUpload $component, TemporaryUploadedFile $file) => app(MediaProcessor::class)
                        ->store($file, $component->getDirectory(), $component->getDiskName())
                        ->disk_path)
                    ->nullable(),

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
