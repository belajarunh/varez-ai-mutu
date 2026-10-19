<?php

namespace App\Filament\Resources\InstrumentVersions\Pages;

use App\Filament\Resources\InstrumentVersions\InstrumentVersionResource;
use App\Models\InstrumentVersion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstrumentVersion extends EditRecord
{
    protected static string $resource = InstrumentVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InstrumentVersion $record): bool => $record->status === InstrumentVersion::STATUS_DRAFT && ! $record->projects()->exists()),
        ];
    }
}
