<?php

namespace App\Filament\Resources\AccreditationBodies\Pages;

use App\Filament\Resources\AccreditationBodies\AccreditationBodyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccreditationBodies extends ListRecords
{
    protected static string $resource = AccreditationBodyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
