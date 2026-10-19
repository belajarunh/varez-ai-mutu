<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'instrument_version_id', 'parent_id', 'node_type', 'code', 'title', 'guidance_text',
    'answer_type', 'validation_json', 'weight', 'sort_order', 'required',
])]
class InstrumentNode extends Model
{
    public const TYPES = ['standard', 'criterion', 'component', 'dimension', 'indicator', 'item', 'subitem'];

    protected function casts(): array
    {
        return [
            'validation_json' => 'array',
            'weight' => 'decimal:4',
            'required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $node): void {
            $versionId = $node->instrument_version_id;
            if (InstrumentVersion::query()->whereKey($versionId)->value('status') !== InstrumentVersion::STATUS_DRAFT) {
                throw new LogicException('Nodes can only be changed while their instrument version is draft.');
            }

            if ($node->parent_id !== null) {
                $parent = self::query()->find($node->parent_id);
                if ($parent === null || (int) $parent->instrument_version_id !== (int) $versionId) {
                    throw new LogicException('Instrument node parent must belong to the same version.');
                }

                $visited = [];
                $parentId = $node->parent_id;
                while ($parentId !== null) {
                    if ((int) $parentId === (int) $node->getKey() || in_array((int) $parentId, $visited, true)) {
                        throw new LogicException('Instrument node hierarchy cannot contain a cycle.');
                    }
                    $visited[] = (int) $parentId;
                    $parentId = self::query()->whereKey($parentId)->value('parent_id');
                }
            }

            $duplicate = self::query()
                ->where('instrument_version_id', $versionId)
                ->where('code', $node->code)
                ->where('parent_id', $node->parent_id);
            if ($node->exists) {
                $duplicate->whereKeyNot($node->getKey());
            }
            if ($duplicate->exists()) {
                throw new LogicException('Node codes must be unique among siblings in one instrument version.');
            }
        });

        static::deleting(function (self $node): void {
            if ($node->version()->value('status') !== InstrumentVersion::STATUS_DRAFT) {
                throw new LogicException('Nodes can only be deleted while their instrument version is draft.');
            }
        });
    }

    /** @return BelongsTo<InstrumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(InstrumentVersion::class, 'instrument_version_id');
    }

    /** @return BelongsTo<InstrumentNode, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<InstrumentNode, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<InstrumentNodeRule, $this> */
    public function rules(): HasMany
    {
        return $this->hasMany(InstrumentNodeRule::class);
    }
}
