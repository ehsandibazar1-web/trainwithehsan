<?php

namespace App\Filament\Resources\ContentPlans;

use App\Filament\Resources\ContentPlans\Pages\CreateContentPlan;
use App\Filament\Resources\ContentPlans\Pages\EditContentPlan;
use App\Filament\Resources\ContentPlans\Pages\ListContentPlans;
use App\Filament\Resources\ContentPlans\Schemas\ContentPlanForm;
use App\Filament\Resources\ContentPlans\Tables\ContentPlanTable;
use App\Models\ContentPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * فرم Create/Edit این ریسورس همان چیزی است که Content Planner (Kanban/Calendar/Table) برای
 * افزودن/ویرایش یک کارت به آن لینک می‌دهد — این ریسورس عمداً از نویگیشن پنهان است چون خودِ
 * صفحه‌ی Planner مرکز اصلی است، نه یک لیست جدا؛ نگاه کنید به App\Filament\Pages\ContentPlanner.
 */
class ContentPlanResource extends Resource
{
    protected static ?string $model = ContentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content Planner';

    protected static ?string $recordTitleAttribute = 'title';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ContentPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentPlanTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentPlans::route('/'),
            'create' => CreateContentPlan::route('/create'),
            'edit' => EditContentPlan::route('/{record}/edit'),
        ];
    }
}
