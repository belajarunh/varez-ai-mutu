<?php

namespace App\Filament\Resources\InstrumentNodeRules\Pages;

use App\Filament\Resources\InstrumentNodeRules\InstrumentNodeRuleResource;
use App\Models\InstrumentNodeRule;
use App\Models\InstrumentVersion;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInstrumentNodeRule extends EditRecord
{
    protected static string $resource = InstrumentNodeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (InstrumentNodeRule $record): bool => $record->node()->first()?->version()->value('status') === InstrumentVersion::STATUS_DRAFT),
        ];
    }
}
