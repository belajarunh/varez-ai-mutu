<?php

namespace App\Filament\Resources\InstrumentNodes\Schemas;

use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InstrumentNodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instrument_version_id')
                    ->label('Versi instrumen')
                    ->live()
                    ->options(fn (): array => InstrumentVersion::query()
                        ->where('status', InstrumentVersion::STATUS_DRAFT)
                        ->with('instrument')
                        ->get()
                        ->mapWithKeys(fn (InstrumentVersion $version): array => [
                            $version->getKey() => $version->instrument->name.' · '.$version->version_label,
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                Select::make('parent_id')
                    ->label('Node induk')
                    ->options(fn (Get $get): array => InstrumentNode::query()
                        ->where('instrument_version_id', $get('instrument_version_id'))
                        ->orderBy('sort_order')
                        ->orderBy('code')
                        ->pluck('title', 'id')
                        ->all())
                    ->searchable()
                    ->nullable(),
                Select::make('node_type')
                    ->options(array_combine(InstrumentNode::TYPES, InstrumentNode::TYPES))
                    ->required(),
                TextInput::make('code')->required()->maxLength(100),
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('guidance_text')->rows(3)->columnSpanFull(),
                Select::make('answer_type')
                    ->options([
                        'text' => 'Teks',
                        'number' => 'Angka',
                        'boolean' => 'Ya/tidak',
                        'date' => 'Tanggal',
                        'json' => 'Terstruktur',
                    ])
                    ->nullable(),
                KeyValue::make('validation_json')
                    ->keyLabel('Kunci aturan')
                    ->valueLabel('Nilai')
                    ->columnSpanFull(),
                TextInput::make('weight')->numeric()->minValue(0)->maxValue(1000000),
                TextInput::make('sort_order')->numeric()->integer()->minValue(0)->default(0)->required(),
                Toggle::make('required')->default(false),
            ]);
    }
}
