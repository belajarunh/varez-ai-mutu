<?php

namespace App\Models;

use App\Services\Performance\PerformanceDatasetSchemaDefinition;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<int, array<string, mixed>> $fields_json
 */
#[Fillable(['dataset_type', 'schema_version', 'name', 'description', 'fields_json', 'status', 'created_by'])]
class PerformanceDatasetSchema extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_RETIRED = 'retired';

    public const FIELD_TYPES = ['string', 'integer', 'decimal', 'date', 'boolean'];

    protected function casts(): array
    {
        return ['fields_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $schema): void {
            app(PerformanceDatasetSchemaDefinition::class)
                ->validate($schema->dataset_type, $schema->fields_json ?? []);
        });

        static::updating(function (self $schema): void {
            if (
                in_array($schema->getOriginal('status'), [self::STATUS_PUBLISHED, self::STATUS_RETIRED], true)
                && $schema->isDirty(array_keys($schema->getDirty()))
                && ! ($schema->isDirty('status') && count($schema->getDirty()) === 1)
            ) {
                throw new LogicException('Published dataset schemas are immutable; create a new version instead.');
            }

            if ($schema->getOriginal('status') === self::STATUS_RETIRED && $schema->status !== self::STATUS_RETIRED) {
                throw new LogicException('Retired dataset schemas cannot be reactivated.');
            }
        });

        static::deleting(function (self $schema): void {
            if ($schema->status !== self::STATUS_DRAFT || $schema->importBatches()->exists()) {
                throw new LogicException('Only unused draft dataset schemas may be deleted.');
            }
        });
    }

    /** @return HasMany<ImportBatch, $this> */
    public function importBatches(): HasMany
    {
        return $this->hasMany(ImportBatch::class);
    }

    /** @return HasMany<PerformanceDataset, $this> */
    public function datasets(): HasMany
    {
        return $this->hasMany(PerformanceDataset::class);
    }
}
