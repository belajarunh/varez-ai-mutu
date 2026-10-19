<?php

namespace App\Filament\Resources\InstrumentNodeRules\Pages;

use App\Filament\Resources\InstrumentNodeRules\InstrumentNodeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstrumentNodeRules extends ListRecords
{
    protected static string $resource = InstrumentNodeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
