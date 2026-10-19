<?php

namespace App\Filament\Resources\InstrumentNodes\Pages;

use App\Filament\Resources\InstrumentNodes\InstrumentNodeResource;
use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstrumentNode extends EditRecord
{
    protected static string $resource = InstrumentNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InstrumentNode $record): bool => $record->version()->value('status') === InstrumentVersion::STATUS_DRAFT),
        ];
    }
}
