<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class NewsletterSubscriberForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('locale')
                    ->label('Language / Dil')
                    ->options([
                        'en' => 'English',
                        'tr' => 'Türkçe',
                    ])
                    ->required(),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'subscribed' => 'Subscribed',
                        'unsubscribed' => 'Unsubscribed',
                    ])
                    ->required(),

                DateTimePicker::make('verified_at')
                    ->label('Verified at')
                    ->nullable()
                    ->helperText('Empty = the subscriber has not confirmed their email yet. They only receive newsletters after confirming.'),

                TextInput::make('source')
                    ->label('Source')
                    ->disabled(),

                TextInput::make('ip_address')
                    ->label('IP address')
                    ->disabled(),
            ]);
    }
}
