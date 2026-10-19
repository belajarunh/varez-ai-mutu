<?php

namespace App\Filament\Resources\InstrumentNodeRules\Tables;

use App\Models\InstrumentNodeRule;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InstrumentNodeRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('node.version.instrument.name')->label('Instrumen'),
                TextColumn::make('node.code')->label('Kode node')->searchable(),
                TextColumn::make('rule_type')->searchable(),
                TextColumn::make('severity')->badge(),
                TextColumn::make('message')->limit(60),
            ])
            ->filters([
                SelectFilter::make('severity')->options([
                    'info' => 'Info',
                    'warning' => 'Peringatan',
                    'error' => 'Kesalahan',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InstrumentNodeRule $record): bool => $record->node()->first()?->version()->value('status') === 'draft'),
            ]);
    }
}
