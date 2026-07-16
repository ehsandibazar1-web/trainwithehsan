<?php

namespace App\Filament\Resources\AiTemplates\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AiTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Template name')
                    ->required()
                    ->helperText('e.g. "Standard blog article (EN)" — shown in the template picker on the AI Import page.'),

                TextInput::make('description')
                    ->label('Description')
                    ->nullable(),

                Select::make('format')
                    ->label('Format')
                    ->options([
                        'json' => 'JSON',
                        'markdown' => 'Markdown',
                    ])
                    ->default('json')
                    ->required(),

                Textarea::make('content')
                    ->label('Template content')
                    ->rows(16)
                    ->required()
                    ->helperText('The skeleton that is loaded into the paste area on the AI Import page. Leave placeholder values where the AI-generated content goes.')
                    ->columnSpanFull(),
            ]);
    }
}
