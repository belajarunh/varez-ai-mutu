<?php

namespace App\Filament\Resources\InstrumentNodes;

use App\Filament\Resources\InstrumentNodes\Pages\CreateInstrumentNode;
use App\Filament\Resources\InstrumentNodes\Pages\EditInstrumentNode;
use App\Filament\Resources\InstrumentNodes\Pages\ListInstrumentNodes;
use App\Filament\Resources\InstrumentNodes\Schemas\InstrumentNodeForm;
use App\Filament\Resources\InstrumentNodes\Tables\InstrumentNodesTable;
use App\Models\InstrumentNode;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstrumentNodeResource extends Resource
{
    protected static ?string $model = InstrumentNode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InstrumentNodeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstrumentNodesTable::configure($table);
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
            'index' => ListInstrumentNodes::route('/'),
            'create' => CreateInstrumentNode::route('/create'),
            'edit' => EditInstrumentNode::route('/{record}/edit'),
        ];
    }
}
