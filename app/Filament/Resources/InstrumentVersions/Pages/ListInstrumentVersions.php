<?php

namespace App\Filament\Resources\InstrumentVersions\Pages;

use App\Filament\Resources\InstrumentVersions\InstrumentVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInstrumentVersions extends ListRecords
{
    protected static string $resource = InstrumentVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
