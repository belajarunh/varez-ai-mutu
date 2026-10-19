<?php

namespace App\Filament\Resources\PerformanceDatasetSchemas\Tables;

use App\Models\AuditLog;
use App\Models\PerformanceDatasetSchema;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PerformanceDatasetSchemasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dataset_type')->label('Jenis')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('schema_version')->label('Versi')->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    PerformanceDatasetSchema::STATUS_DRAFT => 'Draft',
                    PerformanceDatasetSchema::STATUS_PUBLISHED => 'Terbit',
                    PerformanceDatasetSchema::STATUS_RETIRED => 'Pensiun',
                ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (PerformanceDatasetSchema $record): bool => $record->status === PerformanceDatasetSchema::STATUS_DRAFT),
                Action::make('publish')
                    ->label('Terbitkan')
                    ->requiresConfirmation()
                    ->visible(fn (PerformanceDatasetSchema $record): bool => $record->status === PerformanceDatasetSchema::STATUS_DRAFT)
                    ->action(function (PerformanceDatasetSchema $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User && $user->hasPlatformRole('platform.super_admin'), 403);

                        $record->update(['status' => PerformanceDatasetSchema::STATUS_PUBLISHED]);
                        AuditLog::query()->create([
                            'actor_user_id' => $user->getKey(),
                            'action' => 'performance_dataset_schema.published',
                            'subject_type' => PerformanceDatasetSchema::class,
                            'subject_id' => (string) $record->getKey(),
                            'after_json' => [
                                'dataset_type' => $record->dataset_type,
                                'schema_version' => $record->schema_version,
                            ],
                            'correlation_id' => (string) Str::uuid(),
                        ]);
                        Notification::make()->success()->title('Schema dataset diterbitkan')->send();
                    }),
                Action::make('retire')
                    ->label('Pensiunkan')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PerformanceDatasetSchema $record): bool => $record->status === PerformanceDatasetSchema::STATUS_PUBLISHED)
                    ->action(function (PerformanceDatasetSchema $record): void {
                        $user = auth()->user();
                        abort_unless($user instanceof User && $user->hasPlatformRole('platform.super_admin'), 403);

                        $record->update(['status' => PerformanceDatasetSchema::STATUS_RETIRED]);
                        AuditLog::query()->create([
                            'actor_user_id' => $user->getKey(),
                            'action' => 'performance_dataset_schema.retired',
                            'subject_type' => PerformanceDatasetSchema::class,
                            'subject_id' => (string) $record->getKey(),
                            'after_json' => [
                                'dataset_type' => $record->dataset_type,
                                'schema_version' => $record->schema_version,
                            ],
                            'correlation_id' => (string) Str::uuid(),
                        ]);
                        Notification::make()->success()->title('Schema dataset dipensiunkan')->send();
                    }),
            ]);
    }
}
