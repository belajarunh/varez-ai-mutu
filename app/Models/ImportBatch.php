<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $institution_id
 * @property int $uploaded_by
 * @property int|null $confirmed_by
 * @property int $performance_dataset_schema_id
 * @property int $academic_period_id
 * @property int|null $unit_id
 * @property int|null $target_dataset_id
 * @property string $source_type
 * @property string $storage_key
 * @property string $checksum_sha256
 * @property string $status
 * @property string|null $strategy
 * @property int $row_count
 * @property int $valid_count
 * @property int $error_count
 * @property int $fatal_error_count
 * @property array<string, string>|null $mapping_json
 * @property list<string>|null $headers_json
 * @property array<int, array<string, mixed>>|null $preview_json
 */
#[Fillable([
    'institution_id', 'uploaded_by', 'confirmed_by', 'performance_dataset_schema_id', 'academic_period_id', 'unit_id',
    'target_dataset_id', 'source_type', 'original_filename', 'storage_key', 'checksum_sha256', 'status',
    'strategy', 'mapping_json', 'row_count', 'valid_count', 'error_count', 'fatal_error_count', 'started_at',
    'finished_at', 'confirmed_at', 'preview_json', 'headers_json',
])]
class ImportBatch extends Model
{
    public const STATUS_AWAITING_MAPPING = 'awaiting_mapping';

    public const STATUS_INSPECTING = 'inspecting';

    public const STATUS_VALIDATING = 'validating';

    public const STATUS_PREVIEW_READY = 'preview_ready';

    public const STATUS_PREVIEW_WITH_ERRORS = 'preview_with_errors';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STRATEGY_APPEND = 'append';

    public const STRATEGY_REPLACE = 'replace';

    protected function casts(): array
    {
        return [
            'mapping_json' => 'array',
            'preview_json' => 'array',
            'headers_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** @return BelongsTo<PerformanceDatasetSchema, $this> */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(PerformanceDatasetSchema::class, 'performance_dataset_schema_id');
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /** @return BelongsTo<PerformanceDataset, $this> */
    public function targetDataset(): BelongsTo
    {
        return $this->belongsTo(PerformanceDataset::class, 'target_dataset_id');
    }

    /** @return HasMany<ImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
