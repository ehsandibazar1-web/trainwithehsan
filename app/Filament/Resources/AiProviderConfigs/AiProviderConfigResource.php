<?php

namespace App\Filament\Resources\AiProviderConfigs;

use App\Filament\Resources\AiProviderConfigs\Pages\EditAiProviderConfig;
use App\Filament\Resources\AiProviderConfigs\Pages\ListAiProviderConfigs;
use App\Filament\Resources\AiProviderConfigs\Schemas\AiProviderConfigForm;
use App\Filament\Resources\AiProviderConfigs\Tables\AiProviderConfigsTable;
use App\Models\AiProviderConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * پنج ردیف این جدول با migration seed می‌شوند (یکی به‌ازای هر کلاس در
 * App\Services\AiAssistant\ProviderManager::DRIVERS) — عمداً هیچ Create/Delete page‌ای وجود
 * ندارد، چون slug باید دقیقاً با یکی از کلیدهای DRIVERS برابر باشد؛ افزودن ارائه‌دهنده‌ی ششم یک
 * کلاس Provider تازه + یک ردیف migration/seed تازه می‌خواهد، نه یک فرم «ایجاد» در همین صفحه.
 */
class AiProviderConfigResource extends Resource
{
    protected static ?string $model = AiProviderConfig::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'AI Providers';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AiProviderConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiProviderConfigsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiProviderConfigs::route('/'),
            'edit' => EditAiProviderConfig::route('/{record}/edit'),
        ];
    }
}
