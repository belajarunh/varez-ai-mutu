<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'institution_id', 'activity_id', 'document_id', 'document_version_id', 'linked_by', 'note',
])]
class PpeppEvidence extends Model
{
    /** @return BelongsTo<PpeppActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(PpeppActivity::class, 'activity_id');
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

    /** @return BelongsTo<User, $this> */
    public function linker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}
