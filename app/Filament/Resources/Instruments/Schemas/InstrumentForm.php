<?php

namespace App\Filament\Resources\Instruments\Schemas;

use App\Models\AccreditationBody;
use App\Models\Instrument;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InstrumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('domain_type')
                    ->options([
                        'higher_education' => 'Perguruan tinggi',
                        'school' => 'Sekolah',
                    ])
                    ->live()
                    ->required(),
                Select::make('accreditation_body_id')
                    ->label('Lembaga')
                    ->options(fn (Get $get): array => AccreditationBody::query()
                        ->where('domain_type', $get('domain_type'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->required(),
                TextInput::make('code')->required()->maxLength(100),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('instrument_type')
                    ->required()
                    ->maxLength(80)
                    ->helperText('Jenis instrumen ditentukan oleh katalog yang disepakati.'),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('status')
                    ->options([
                        Instrument::STATUS_INTERNAL => 'Internal',
                        Instrument::STATUS_PUBLISHED => 'Terbit',
                        Instrument::STATUS_RETIRED => 'Pensiun',
                    ])
                    ->default(Instrument::STATUS_INTERNAL)
                    ->required()
                    ->helperText('Status terbit tidak membuat versi resmi dengan sendirinya; versi tetap harus diverifikasi dan diterbitkan.'),
            ]);
    }
}
