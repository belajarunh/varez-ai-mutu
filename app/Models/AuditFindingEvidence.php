<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'finding_id', 'document_id', 'document_version_id', 'linked_by', 'note',
])]
class AuditFindingEvidence extends Model
{
    /** @return BelongsTo<AuditFinding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'finding_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
