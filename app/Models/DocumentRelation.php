<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['institution_id', 'document_id', 'related_type', 'related_id', 'relation_type', 'created_by'])]
class DocumentRelation extends Model
{
    public const RELATED_PROJECT = 'project';

    public const RELATED_PROJECT_INDICATOR = 'project_indicator';

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
