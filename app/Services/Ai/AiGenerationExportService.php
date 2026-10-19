<?php

namespace App\Services\Ai;

use App\Models\AiGeneration;
use App\Models\AiGenerationExport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AiGenerationExportService
{
    public function export(AiGeneration $generation, User $user, string $format = 'json'): array
    {
        if (! $generation->canBeExportedBy($user)) {
            throw new AuthorizationException('User cannot export this AI generation.');
        }

        $watermarked = $generation->status !== AiGeneration::STATUS_APPROVED;

        $generation->save();

        $manifest = [
            'generation_id' => $generation->getKey(),
            'status' => $generation->status,
            'watermarked' => $watermarked,
            'exported_by' => $user->getKey(),
            'citations' => $generation->citations()->get()->map(fn ($citation) => [
                'key' => $citation->citation_key,
                'label' => $citation->display_label,
                'page_or_section' => $citation->page_or_section,
                'quote' => $citation->quoted_text,
            ])->all(),
            'source_manifest' => $generation->sources()->get()->map(fn ($source) => [
                'chunk_id' => $source->document_chunk_id,
                'document_version_id' => $source->document_version_id,
                'rank' => $source->rank,
                'relevance_score' => $source->relevance_score,
            ])->all(),
            'watermark' => $watermarked ? 'DRAFT-AI-UNAPPROVED' : null,
        ];

        $record = AiGenerationExport::query()->create([
            'ai_generation_id' => $generation->getKey(),
            'exported_by_user_id' => $user->getKey(),
            'format' => $format,
            'file_name' => 'ai-generation-'.$generation->getKey().'.'.$format,
            'manifest_json' => $manifest,
            'watermarked' => $manifest['watermarked'],
            'exported_at' => now(),
        ]);

        $generation->status = AiGeneration::STATUS_EXPORTED;
        $generation->save();

        return [
            'id' => $record->getKey(),
            'generation_id' => $generation->getKey(),
            'status' => $generation->status,
            'manifest' => $manifest,
            'watermarked' => $manifest['watermarked'],
            'download' => $record->file_name,
        ];
    }
}
