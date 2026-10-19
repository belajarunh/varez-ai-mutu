<?php

namespace App\Filament\Resources\AccreditationBodies\Schemas;

use App\Models\AccreditationBody;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AccreditationBodyForm
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
                    ->required(),
                TextInput::make('code')->required()->maxLength(80),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('official_url')
                    ->url()
                    ->maxLength(2048)
                    ->helperText('Masukkan referensi yang dapat diverifikasi; jangan mengisi URL rekaan.')
                    ->required(fn ($get): bool => $get('status') === AccreditationBody::STATUS_VERIFIED),
                Select::make('status')
                    ->options([
                        AccreditationBody::STATUS_DRAFT => 'Draft',
                        AccreditationBody::STATUS_INTERNAL => 'Internal/demo',
                        AccreditationBody::STATUS_VERIFIED => 'Terverifikasi',
                        AccreditationBody::STATUS_INACTIVE => 'Tidak aktif',
                    ])
                    ->default(AccreditationBody::STATUS_DRAFT)
                    ->required(),
            ]);
    }
}
