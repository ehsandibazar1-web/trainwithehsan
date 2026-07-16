<?php

namespace App\Filament\Resources\ApiTokens\Pages;

use App\Filament\Resources\ApiTokens\ApiTokenResource;
use App\Models\ApiToken;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateApiToken extends CreateRecord
{
    protected static string $resource = ApiTokenResource::class;

    protected ?string $plainToken = null;

    // متن کامل توکن فقط همین‌جا وجود دارد — در دیتابیس فقط هَش ذخیره می‌شود
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->plainToken = ApiToken::generatePlainToken();

        $data['token_hash'] = hash('sha256', $this->plainToken);
        $data['prefix'] = substr($this->plainToken, 0, 12);

        return $data;
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->success()
            ->persistent()
            ->title('API token created — copy it NOW, it will not be shown again')
            ->body($this->plainToken)
            ->send();
    }
}
