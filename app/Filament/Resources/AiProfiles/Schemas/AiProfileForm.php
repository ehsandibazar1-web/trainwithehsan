<?php

namespace App\Filament\Resources\AiProfiles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AiProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Profile name')
                    ->required()
                    ->helperText('e.g. "Claude — English articles". Pick this profile on the AI Import page.'),

                TextInput::make('provider')
                    ->label('AI provider')
                    ->required()
                    ->helperText('e.g. claude, chatgpt, gemini — recorded in the import history for every import made with this profile.'),

                Select::make('default_language')
                    ->label('Default language')
                    ->options(['en' => 'English', 'tr' => 'Türkçe'])
                    ->nullable()
                    ->helperText('Used only when the pasted content does not specify a language itself.'),

                Select::make('default_status')
                    ->label('Default publish status')
                    ->options([
                        'draft' => 'Draft',
                        'scheduled' => 'Scheduled',
                        'published' => 'Published',
                    ])
                    ->nullable()
                    ->helperText('Used only when the pasted content does not specify a status itself.'),

                TextInput::make('default_category')
                    ->label('Default category')
                    ->nullable(),

                TextInput::make('default_author')
                    ->label('Default author')
                    ->nullable(),

                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->nullable()
                    ->columnSpanFull(),
            ]);
    }
}
