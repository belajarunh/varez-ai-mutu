<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas\Schemas;

use App\Models\PerformanceDatasetSchema;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PerformanceDatasetSchemaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('dataset_type')
                    ->label('Kode jenis dataset')
                    ->helperText('Kode teknis huruf kecil/angka/garis bawah, misalnya sesuai domain yang disetujui.')
                    ->required()
                    ->maxLength(100)
                    ->disabled(fn (?PerformanceDatasetSchema $record): bool => $record !== null),
                TextInput::make('schema_version')
                    ->label('Versi schema')
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->disabled(fn (?PerformanceDatasetSchema $record): bool => $record !== null),
                TextInput::make('name')->label('Nama')->required()->maxLength(160),
                Textarea::make('description')->rows(2)->columnSpanFull(),
                Repeater::make('fields_json')
                    ->label('Field data')
                    ->schema([
                        TextInput::make('key')
                            ->label('Key')
                            ->helperText('Harus stabil dan unik di dalam schema.')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('label')->label('Label')->required()->maxLength(150),
                        Select::make('type')
                            ->options(array_combine(
                                PerformanceDatasetSchema::FIELD_TYPES,
                                PerformanceDatasetSchema::FIELD_TYPES,
                            ))
                            ->required(),
                        Toggle::make('required')->default(false),
                        Toggle::make('unique')->default(false),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->maxItems(100)
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->helperText('Schema wajib memiliki field record_key string yang required dan unique. Setelah terbit, buat versi baru untuk perubahan.'),
            ]);
    }
}
