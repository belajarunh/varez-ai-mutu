<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas;

use App\Filament\Resources\PerformanceDatasetSchemas\Pages\CreatePerformanceDatasetSchema;
use App\Filament\Resources\PerformanceDatasetSchemas\Pages\EditPerformanceDatasetSchema;
use App\Filament\Resources\PerformanceDatasetSchemas\Pages\ListPerformanceDatasetSchemas;
use App\Filament\Resources\PerformanceDatasetSchemas\Schemas\PerformanceDatasetSchemaForm;
use App\Filament\Resources\PerformanceDatasetSchemas\Tables\PerformanceDatasetSchemasTable;
use App\Models\PerformanceDatasetSchema;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PerformanceDatasetSchemaResource extends Resource
{
    protected static ?string $model = PerformanceDatasetSchema::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Schema data kinerja';

    protected static ?string $modelLabel = 'schema dataset';

    protected static ?string $pluralModelLabel = 'schema dataset';

    public static function form(Schema $schema): Schema
    {
        return PerformanceDatasetSchemaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PerformanceDatasetSchemasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPerformanceDatasetSchemas::route('/'),
            'create' => CreatePerformanceDatasetSchema::route('/create'),
            'edit' => EditPerformanceDatasetSchema::route('/{record}/edit'),
        ];
    }
}
