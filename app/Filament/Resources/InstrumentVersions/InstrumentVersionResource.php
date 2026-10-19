<?php

namespace App\Filament\Resources\InstrumentVersions;

use App\Filament\Resources\InstrumentVersions\Pages\CreateInstrumentVersion;
use App\Filament\Resources\InstrumentVersions\Pages\EditInstrumentVersion;
use App\Filament\Resources\InstrumentVersions\Pages\ListInstrumentVersions;
use App\Filament\Resources\InstrumentVersions\Schemas\InstrumentVersionForm;
use App\Filament\Resources\InstrumentVersions\Tables\InstrumentVersionsTable;
use App\Models\InstrumentVersion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstrumentVersionResource extends Resource
{
    protected static ?string $model = InstrumentVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InstrumentVersionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstrumentVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInstrumentVersions::route('/'),
            'create' => CreateInstrumentVersion::route('/create'),
            'edit' => EditInstrumentVersion::route('/{record}/edit'),
        ];
    }
}
