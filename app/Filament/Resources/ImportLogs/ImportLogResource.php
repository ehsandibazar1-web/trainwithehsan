<?php

namespace App\Filament\Resources\ImportLogs;

use App\Filament\Resources\ImportLogs\Pages\ListImportLogs;
use App\Filament\Resources\ImportLogs\Tables\ImportLogsTable;
use App\Models\ImportLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ImportLogResource extends Resource
{
    protected static ?string $model = ImportLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Import History';

    protected static ?string $modelLabel = 'import';

    protected static ?string $pluralModelLabel = 'Import History';

    public static function table(Table $table): Table
    {
        return ImportLogsTable::configure($table);
    }

    // تاریخچه فقط-خواندنی است — ایمپورت جدید فقط از صفحه‌ی AI Import انجام می‌شود
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImportLogs::route('/'),
        ];
    }
}
