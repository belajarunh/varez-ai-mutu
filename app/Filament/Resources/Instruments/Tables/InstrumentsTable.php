<?php

namespace App\Filament\Resources\Instruments\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstrumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('accreditationBody.name')->label('Lembaga')->searchable(),
                TextColumn::make('domain_type')->badge(),
                TextColumn::make('instrument_type'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('domain_type')->options([
                    'higher_education' => 'Perguruan tinggi',
                    'school' => 'Sekolah',
                ]),
                SelectFilter::make('status')->options([
                    'internal' => 'Internal',
                    'published' => 'Terbit',
                    'retired' => 'Pensiun',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
