<?php

namespace App\Filament\Resources\KnowledgeEntries\Schemas;

use App\Models\KnowledgeEntry;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

// دسته‌بندی‌های پیشنهادی که کاربر در متن درخواست فهرست کرده — فقط پیشنهاد (datalist)، نه یک
// enum بسته؛ ادمین می‌تواند هر متن دلخواه دیگری هم تایپ کند (مثل category روی Article/ContentPlan)
class KnowledgeEntryForm
{
    private const SUGGESTED_CATEGORIES = [
        'Biography', 'Services', 'Policies', 'Courses', 'Martial Arts', 'Locations',
        'FAQs', 'Products', 'Business Information', 'Contact Information', 'Training Methods',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('category')
                    ->label('Category')
                    ->required()
                    ->datalist(self::SUGGESTED_CATEGORIES)
                    ->helperText('Pick a suggestion or type your own — e.g. Biography, Services, Locations, Training Methods.'),

                Select::make('locale')
                    ->label('Language')
                    ->options(['en' => 'English', 'tr' => 'Türkçe'])
                    ->default('en')
                    ->required()
                    ->helperText('Which language this entry is written in — only used for content in that same language.'),

                Textarea::make('content')
                    ->label('Content')
                    ->required()
                    ->rows(8)
                    ->columnSpanFull()
                    ->helperText('The actual fact/knowledge — the AI Assistant reads this when it decides this entry is relevant to what it\'s writing.'),

                TextInput::make('source')
                    ->label('Source')
                    ->nullable()
                    ->columnSpanFull()
                    ->helperText('Optional — where this came from (a document, a conversation, a URL) for your own reference.'),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        KnowledgeEntry::STATUS_DRAFT => 'Draft',
                        KnowledgeEntry::STATUS_ACTIVE => 'Active',
                        KnowledgeEntry::STATUS_ARCHIVED => 'Archived',
                    ])
                    ->default(KnowledgeEntry::STATUS_ACTIVE)
                    ->required()
                    ->helperText('Only "Active" entries are ever used by the AI Assistant.'),

                Select::make('priority')
                    ->label('Priority')
                    ->options([
                        KnowledgeEntry::PRIORITY_LOW => 'Low',
                        KnowledgeEntry::PRIORITY_MEDIUM => 'Medium',
                        KnowledgeEntry::PRIORITY_HIGH => 'High',
                        KnowledgeEntry::PRIORITY_CRITICAL => 'Critical',
                    ])
                    ->default(KnowledgeEntry::PRIORITY_MEDIUM)
                    ->required()
                    ->helperText('Higher priority entries are favored when the AI picks which facts to use.'),

                Toggle::make('is_pinned')
                    ->label('Always include')
                    ->helperText('Pinned entries are always given to the AI for content in their language, regardless of topic relevance — use for must-know facts (e.g. business name, core policies).'),

                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->nullable()
                    ->helperText('Optional — after this date/time, this entry is automatically excluded from AI generation (e.g. a seasonal promotion).'),

                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Tags')
                    ->columnSpanFull()
                    ->helperText('Optional — improves keyword-based matching when the AI shortlists relevant entries.')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Tag name')
                            ->required(),
                    ]),

                Repeater::make('attachments')
                    ->relationship()
                    ->label('Existing attachments')
                    ->schema([
                        TextInput::make('original_filename')
                            ->label('File')
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->addable(false)
                    ->deletable()
                    ->reorderable(false)
                    ->collapsed()
                    ->itemLabel(fn (array $state): ?string => $state['original_filename'] ?? null)
                    ->columnSpanFull()
                    ->visibleOn('edit'),

                FileUpload::make('new_attachments')
                    ->label('Add attachments')
                    ->multiple()
                    ->disk('public')
                    ->directory('knowledge-base')
                    ->preserveFilenames()
                    ->acceptedFileTypes(['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain', 'text/html', 'text/markdown'])
                    ->columnSpanFull()
                    ->dehydrated()
                    ->helperText('Upload PDFs, Word docs, TXT, HTML, or Markdown files for AI/admin reference — images belong in the Media Library instead. Every file is automatically extracted and indexed for AI retrieval (RAG).'),

                TextInput::make('new_website_url')
                    ->label('Or add a website page by URL')
                    ->url()
                    ->nullable()
                    ->columnSpanFull()
                    ->dehydrated()
                    ->helperText('The page is fetched, its text extracted, and indexed for AI retrieval — same as an uploaded document, just sourced from a live URL.'),
            ]);
    }
}
