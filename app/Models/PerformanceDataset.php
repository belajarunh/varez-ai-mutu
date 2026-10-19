<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'institution_id', 'unit_id', 'academic_period_id', 'dataset_type', 'schema_version',
    'performance_dataset_schema_id', 'source_import_batch_id', 'status',
])]
class PerformanceDataset extends Model
{
    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<OrganizationalUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(OrganizationalUnit::class, 'unit_id');
    }

    /** @return BelongsTo<AcademicPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /** @return BelongsTo<PerformanceDatasetSchema, $this> */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(PerformanceDatasetSchema::class, 'performance_dataset_schema_id');
    }

    /** @return HasMany<PerformanceRecord, $this> */
    public function records(): HasMany
    {
        return $this->hasMany(PerformanceRecord::class, 'dataset_id');
    }
}
