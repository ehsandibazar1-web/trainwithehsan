<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Models\Page;
use App\Services\Media\MediaProcessor;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

                RichEditor::make('body')
                    ->label('Page content')
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('pages/inline')
                    ->columnSpanFull(),

                FileUpload::make('image_path')
                    ->label('Featured image')
                    ->helperText('Automatically added to the Media Library (WebP + thumbnail + responsive sizes generated).')
                    ->image()
                    ->disk('public')
                    ->directory('pages')
                    // ثبت خودکار در کتابخانه‌ی رسانه (DAM) + تولید WebP/تامبنیل/سایزهای واکنش‌گرا در همان لحظه‌ی آپلود
                    ->saveUploadedFileUsing(fn (BaseFileUpload $component, TemporaryUploadedFile $file) => app(MediaProcessor::class)
                        ->store($file, $component->getDirectory(), $component->getDiskName())
                        ->disk_path)
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
                    ->helperText('For "Scheduled": set a future date/time — the page goes live automatically at that moment.'),
            ]);
    }
}
