<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string|null $ppepp_stage
 * @property string $access_default
 * @property bool $is_active
 */
#[Fillable(['institution_id', 'code', 'name', 'ppepp_stage', 'access_default', 'is_active'])]
class DocumentCategory extends Model
{
    public const PPEPP_STAGES = ['penetapan', 'pelaksanaan', 'evaluasi', 'pengendalian', 'peningkatan'];

    public const ACCESS_LEVELS = ['private', 'unit', 'institution'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }
}
