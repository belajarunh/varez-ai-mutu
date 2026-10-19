<?php

namespace App\Services\Ai;

use App\Models\DocumentChunk;
use App\Models\Institution;
use Illuminate\Support\Collection;

class RetrievalGuardrails
{
    /**
     * @param  Collection<int, DocumentChunk>  $chunks
     * @return Collection<int, DocumentChunk>
     */
    public function filterAccessible(Collection $chunks, int $institutionId, int $userId): Collection
    {
        return $chunks->filter(function (DocumentChunk $chunk) use ($institutionId, $userId) {
            $version = $chunk->version()->first();
            if ($version === null) {
                return false;
            }

            if ((int) $version->institution_id !== $institutionId) {
                return false;
            }

            if ((int) $chunk->institution_id !== $institutionId) {
                return false;
            }

            $document = $version->document()->first();
            if ($document === null) {
                return false;
            }

            if ((int) $document->institution_id !== $institutionId) {
                return false;
            }

            if ((string) $chunk->index_status !== 'ready') {
                return false;
            }

            return $document->owner_user_id === $userId || $document->access_level !== 'private';
        });
    }

    /** @return array<string, mixed> */
    public function sanitizeContext(string $context): array
    {
        $trimmed = preg_replace('/(?im)^\s*(?:system|ignore|override|you are|recommendation|instruction):\s*/', '', $context);

        return [
            'sanitized_context' => (string) ($trimmed ?? $context),
            'contains_prompt_injection' => (bool) preg_match('/(?i)(ignore previous|override instructions|system prompt|act as|developer mode)/', $context),
        ];
    }

    /** @return array<string, mixed> */
    public function providerStatus(string $providerName): array
    {
        $configured = config("ai.providers.{$providerName}.enabled", false);

        return [
            'provider' => $providerName,
            'configured' => (bool) $configured,
            'status' => $configured ? 'ready' : 'not_configured',
        ];
    }
}
