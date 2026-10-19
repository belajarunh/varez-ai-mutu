<?php

namespace App\Filament\Resources\AccreditationBodies\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccreditationBodiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('domain_type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('official_url')->limit(50)->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('domain_type')->options([
                    'higher_education' => 'Perguruan tinggi',
                    'school' => 'Sekolah',
                ]),
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'internal' => 'Internal/demo',
                    'verified' => 'Terverifikasi',
                    'inactive' => 'Tidak aktif',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
