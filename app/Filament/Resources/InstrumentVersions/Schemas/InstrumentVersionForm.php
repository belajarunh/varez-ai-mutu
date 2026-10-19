<?php

namespace App\Filament\Resources\InstrumentVersions\Schemas;

use App\Models\Instrument;
use App\Models\InstrumentVersion;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InstrumentVersionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('instrument_id')
                    ->options(fn (): array => Instrument::query()
                        ->where('status', '!=', Instrument::STATUS_RETIRED)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabled(fn (?InstrumentVersion $record): bool => $record !== null),
                TextInput::make('version_label')->required()->maxLength(80),
                DatePicker::make('effective_from'),
                DatePicker::make('effective_to')->afterOrEqual('effective_from'),
                Select::make('verification_status')
                    ->options([
                        InstrumentVersion::VERIFICATION_INTERNAL => 'Internal / demo',
                        InstrumentVersion::VERIFICATION_UNVERIFIED => 'Belum diverifikasi',
                        InstrumentVersion::VERIFICATION_VERIFIED => 'Terverifikasi dari sumber',
                    ])
                    ->default(InstrumentVersion::VERIFICATION_INTERNAL)
                    ->required()
                    ->helperText('Hanya versi terverifikasi yang muncul pada pilihan proyek resmi.'),
                TextInput::make('source_url')
                    ->url()
                    ->maxLength(2048)
                    ->required(fn ($get): bool => $get('verification_status') === InstrumentVersion::VERIFICATION_VERIFIED),
                TextInput::make('source_checksum')
                    ->length(64)
                    ->helperText('SHA-256 sumber resmi, bila tersedia.'),
                DatePicker::make('approved_at')
                    ->required(fn ($get): bool => $get('verification_status') === InstrumentVersion::VERIFICATION_VERIFIED),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ]);
    }
}
