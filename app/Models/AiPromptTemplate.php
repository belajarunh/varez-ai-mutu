<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'version', 'task_type', 'system_template', 'user_template', 'output_schema_json', 'status',
])]
class AiPromptTemplate extends Model
{
    /** @return HasMany<AiGeneration, $this> */
    public function generations(): HasMany
    {
        return $this->hasMany(AiGeneration::class, 'prompt_template_id');
    }
}
