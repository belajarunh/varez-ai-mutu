<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas\Pages;

use App\Filament\Resources\PerformanceDatasetSchemas\PerformanceDatasetSchemaResource;
use App\Models\PerformanceDatasetSchema;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceDatasetSchema extends EditRecord
{
    protected static string $resource = PerformanceDatasetSchemaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (PerformanceDatasetSchema $record): bool => $record->status === PerformanceDatasetSchema::STATUS_DRAFT && ! $record->importBatches()->exists()),
        ];
    }
}
