<?php

namespace App\Filament\Resources\AiTemplates;

use App\Filament\Resources\AiTemplates\Pages\CreateAiTemplate;
use App\Filament\Resources\AiTemplates\Pages\EditAiTemplate;
use App\Filament\Resources\AiTemplates\Pages\ListAiTemplates;
use App\Filament\Resources\AiTemplates\Schemas\AiTemplateForm;
use App\Filament\Resources\AiTemplates\Tables\AiTemplatesTable;
use App\Models\AiTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AiTemplateResource extends Resource
{
    protected static ?string $model = AiTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AiTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiTemplates::route('/'),
            'create' => CreateAiTemplate::route('/create'),
            'edit' => EditAiTemplate::route('/{record}/edit'),
        ];
    }
}
