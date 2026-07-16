<?php

namespace App\Filament\Resources\AiUsageLogs;

use App\Filament\Resources\AiUsageLogs\Pages\ListAiUsageLogs;
use App\Filament\Resources\AiUsageLogs\Tables\AiUsageLogsTable;
use App\Models\AiUsageLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

// تاریخچه‌ی فقط-خواندنی مصرف — هر ردیف را فقط App\Services\AiAssistant\ProviderManager::logUsage()
// می‌نویسد، این صفحه هیچ‌وقت آن را ویرایش/حذف نمی‌کند
class AiUsageLogResource extends Resource
{
    protected static ?string $model = AiUsageLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'AI Usage Logs';

    protected static ?string $modelLabel = 'usage log';

    protected static ?string $pluralModelLabel = 'AI Usage Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return AiUsageLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiUsageLogs::route('/'),
        ];
    }
}
