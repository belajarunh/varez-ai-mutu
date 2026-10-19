<?php

namespace App\Filament\Resources\InstrumentNodes\Pages;

use App\Filament\Resources\InstrumentNodes\InstrumentNodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstrumentNodes extends ListRecords
{
    protected static string $resource = InstrumentNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
