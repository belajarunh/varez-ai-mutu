<?php

namespace App\Filament\Resources\AccreditationBodies;

use App\Filament\Resources\AccreditationBodies\Pages\CreateAccreditationBody;
use App\Filament\Resources\AccreditationBodies\Pages\EditAccreditationBody;
use App\Filament\Resources\AccreditationBodies\Pages\ListAccreditationBodies;
use App\Filament\Resources\AccreditationBodies\Schemas\AccreditationBodyForm;
use App\Filament\Resources\AccreditationBodies\Tables\AccreditationBodiesTable;
use App\Models\AccreditationBody;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AccreditationBodyResource extends Resource
{
    protected static ?string $model = AccreditationBody::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return AccreditationBodyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AccreditationBodiesTable::configure($table);
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
            'index' => ListAccreditationBodies::route('/'),
            'create' => CreateAccreditationBody::route('/create'),
            'edit' => EditAccreditationBody::route('/{record}/edit'),
        ];
    }
}
