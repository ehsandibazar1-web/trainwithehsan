<?php

namespace App\Filament\Resources\AiProfiles;

use App\Filament\Resources\AiProfiles\Pages\CreateAiProfile;
use App\Filament\Resources\AiProfiles\Pages\EditAiProfile;
use App\Filament\Resources\AiProfiles\Pages\ListAiProfiles;
use App\Filament\Resources\AiProfiles\Schemas\AiProfileForm;
use App\Filament\Resources\AiProfiles\Tables\AiProfilesTable;
use App\Models\AiProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AiProfileResource extends Resource
{
    protected static ?string $model = AiProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'AI Profiles';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AiProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiProfilesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProfiles::route('/'),
            'create' => CreateAiProfile::route('/create'),
            'edit' => EditAiProfile::route('/{record}/edit'),
        ];
    }
}
