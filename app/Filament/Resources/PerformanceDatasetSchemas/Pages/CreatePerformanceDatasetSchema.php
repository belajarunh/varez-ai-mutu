<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas\Pages;

use App\Filament\Resources\PerformanceDatasetSchemas\PerformanceDatasetSchemaResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceDatasetSchema extends CreateRecord
{
    protected static string $resource = PerformanceDatasetSchemaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';
        $data['created_by'] = auth()->id();

        return $data;
    }
}
