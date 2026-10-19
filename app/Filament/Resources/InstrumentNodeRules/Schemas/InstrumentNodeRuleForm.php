<?php

namespace App\Filament\Resources\InstrumentNodeRules\Schemas;

use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InstrumentNodeRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instrument_node_id')
                    ->label('Node instrumen (versi draft)')
                    ->options(fn (): array => InstrumentNode::query()
                        ->whereHas('version', fn ($query) => $query->where('status', InstrumentVersion::STATUS_DRAFT))
                        ->with('version.instrument')
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (InstrumentNode $node): array => [
                            $node->getKey() => $node->version->instrument->name.' · '.$node->code.' — '.$node->title,
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('rule_type')->required()->maxLength(80),
                KeyValue::make('rule_json')
                    ->keyLabel('Kunci')
                    ->valueLabel('Nilai')
                    ->required()
                    ->columnSpanFull(),
                Select::make('severity')
                    ->options(['info' => 'Info', 'warning' => 'Peringatan', 'error' => 'Kesalahan'])
                    ->default('warning')
                    ->required(),
                TextInput::make('message')->maxLength(500),
            ]);
    }
}
