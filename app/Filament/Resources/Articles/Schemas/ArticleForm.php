<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

                Textarea::make('excerpt')
                    ->label('Excerpt')
                    ->rows(3)
                    ->nullable()
                    ->helperText('Short text shown on the article card in the blog list.'),

                RichEditor::make('body')
                    ->label('Article body')
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('articles/inline')
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Featured image')
                    ->image()
                    ->disk('public')
                    ->directory('articles')
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
