<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['instrument_node_id', 'rule_type', 'rule_json', 'severity', 'message'])]
class InstrumentNodeRule extends Model
{
    protected function casts(): array
    {
        return ['rule_json' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rule): void {
            $status = $rule->node()->first()?->version()->value('status');
            if ($status !== InstrumentVersion::STATUS_DRAFT) {
                throw new LogicException('Rules can only be changed while their instrument version is draft.');
            }
        });

        static::deleting(function (self $rule): void {
            $status = $rule->node()->first()?->version()->value('status');
            if ($status !== InstrumentVersion::STATUS_DRAFT) {
                throw new LogicException('Rules can only be deleted while their instrument version is draft.');
            }
        });
    }

    /** @return BelongsTo<InstrumentNode, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(InstrumentNode::class, 'instrument_node_id');
    }
}
