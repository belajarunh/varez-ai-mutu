<?php

namespace App\Filament\Resources\InstrumentVersions\Tables;

use App\Models\InstrumentVersion;
use App\Models\User;
use App\Services\Instruments\InstrumentVersionPublisher;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class InstrumentVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('instrument.name')->label('Instrumen')->searchable()->sortable(),
                TextColumn::make('version_label')->searchable()->sortable(),
                TextColumn::make('effective_from')->date()->sortable(),
                TextColumn::make('effective_to')->date()->sortable(),
                TextColumn::make('verification_status')->badge(),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'published' => 'Terbit',
                    'retired' => 'Pensiun',
                ]),
                SelectFilter::make('verification_status')->options([
                    'internal' => 'Internal/demo',
                    'unverified' => 'Belum diverifikasi',
                    'verified' => 'Terverifikasi',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (InstrumentVersion $record): bool => $record->status === InstrumentVersion::STATUS_DRAFT),
                Action::make('publish')
                    ->label('Terbitkan')
                    ->requiresConfirmation()
                    ->visible(fn (InstrumentVersion $record): bool => $record->status === InstrumentVersion::STATUS_DRAFT)
                    ->action(function (InstrumentVersion $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);

                        try {
                            app(InstrumentVersionPublisher::class)->publish($record, $user);
                            Notification::make()->success()->title('Versi instrumen diterbitkan')->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->danger()
                                ->title('Versi belum memenuhi syarat terbit')
                                ->body(collect($exception->errors())->flatten()->implode(' '))
                                ->send();
                        }
                    }),
                Action::make('retire')
                    ->label('Pensiunkan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (InstrumentVersion $record): bool => $record->status === InstrumentVersion::STATUS_PUBLISHED)
                    ->action(function (InstrumentVersion $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User, 403);
                        app(InstrumentVersionPublisher::class)->retire($record, $user);
                        Notification::make()->success()->title('Versi dipensiunkan')->send();
                    }),
            ]);
    }
}
