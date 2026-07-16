<?php

namespace App\Filament\Resources\AiPrompts\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AiPromptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Prompt name')
                    ->required()
                    ->helperText('e.g. "Full article in the standard JSON format".'),

                TextInput::make('description')
                    ->label('Description')
                    ->nullable(),

                Textarea::make('prompt')
                    ->label('Prompt text')
                    ->rows(14)
                    ->required()
                    ->helperText('The instruction you give the AI. Copy it from the list with one click whenever you need it.')
                    ->columnSpanFull(),
            ]);
    }
}
