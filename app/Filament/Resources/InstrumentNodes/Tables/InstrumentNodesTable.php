<?php

namespace App\Filament\Resources\InstrumentNodes\Tables;

use App\Models\InstrumentNode;
use App\Models\InstrumentVersion;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstrumentNodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version.instrument.name')->label('Instrumen')->searchable(),
                TextColumn::make('version.version_label')->label('Versi'),
                TextColumn::make('node_type')->badge(),
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('title')->searchable()->limit(80),
                TextColumn::make('parent.code')->label('Kode induk'),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('node_type')->options(array_combine(
                    InstrumentNode::TYPES,
                    InstrumentNode::TYPES,
                )),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InstrumentNode $record): bool => $record->version()->value('status') === InstrumentVersion::STATUS_DRAFT),
            ]);
    }
}
