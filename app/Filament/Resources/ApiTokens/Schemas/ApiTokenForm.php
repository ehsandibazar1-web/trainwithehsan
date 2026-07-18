<?php

namespace App\Filament\Resources\ApiTokens\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApiTokenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Token name')
                    ->required()
                    ->helperText('Who or what uses this token — e.g. "Claude". The token itself is generated automatically and shown ONCE after you save; copy it right away.'),

                DateTimePicker::make('expires_at')
                    ->label('Expires at')
                    ->native(false)
                    ->minDate(now())
                    ->helperText('Optional. Leave empty for a token that never expires.'),
            ]);
    }
}
