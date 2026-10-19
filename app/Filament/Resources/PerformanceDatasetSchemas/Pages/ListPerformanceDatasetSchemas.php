<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas\Pages;

use App\Filament\Resources\PerformanceDatasetSchemas\PerformanceDatasetSchemaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceDatasetSchemas extends ListRecords
{
    protected static string $resource = PerformanceDatasetSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
