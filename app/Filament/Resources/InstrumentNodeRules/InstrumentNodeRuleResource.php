<?php

namespace App\Filament\Resources\InstrumentNodeRules;

use App\Filament\Resources\InstrumentNodeRules\Pages\CreateInstrumentNodeRule;
use App\Filament\Resources\InstrumentNodeRules\Pages\EditInstrumentNodeRule;
use App\Filament\Resources\InstrumentNodeRules\Pages\ListInstrumentNodeRules;
use App\Filament\Resources\InstrumentNodeRules\Schemas\InstrumentNodeRuleForm;
use App\Filament\Resources\InstrumentNodeRules\Tables\InstrumentNodeRulesTable;
use App\Models\InstrumentNodeRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InstrumentNodeRuleResource extends Resource
{
    protected static ?string $model = InstrumentNodeRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return InstrumentNodeRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InstrumentNodeRulesTable::configure($table);
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
            'index' => ListInstrumentNodeRules::route('/'),
            'create' => CreateInstrumentNodeRule::route('/create'),
            'edit' => EditInstrumentNodeRule::route('/{record}/edit'),
        ];
    }
}
