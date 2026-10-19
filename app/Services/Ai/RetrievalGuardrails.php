<?php

namespace App\Services\Ai;

use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Collection;

class RetrievalGuardrails
{
    /**
     * @param  Collection<int, DocumentChunk>  $chunks
     * @return Collection<int, DocumentChunk>
     */
    public function filterAccessible(Collection $chunks, int $institutionId, int $userId): Collection
    {
        $institution = Institution::query()->whereKey($institutionId)->first();
        $user = User::query()->whereKey($userId)->first();

        if ($institution === null || $user === null || ! $user->hasPermission('documents.view', $institution)) {
            return $chunks->filter(static fn (DocumentChunk $chunk): bool => false);
        }

        $accessibleUnitIds = array_fill_keys($user->accessibleUnitIds($institution), true);
        $canManageDocuments = $user->hasPermission('documents.manage', $institution);

        return $chunks->filter(function (DocumentChunk $chunk) use (
            $institutionId,
            $user,
            $accessibleUnitIds,
            $canManageDocuments,
        ): bool {
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

            if ((string) $chunk->index_status !== 'ready'
                || ($document->status !== Document::STATUS_ACTIVE && ! $canManageDocuments)) {
                return false;
            }

            $unitAllowed = $document->unit_id === null
                || isset($accessibleUnitIds[(int) $document->unit_id]);
            $accessAllowed = match ($document->access_level) {
                Document::ACCESS_INSTITUTION => true,
                Document::ACCESS_UNIT => $document->unit_id !== null
                    && isset($accessibleUnitIds[(int) $document->unit_id]),
                Document::ACCESS_PRIVATE => (int) $document->owner_user_id === (int) $user->getKey()
                    || $canManageDocuments,
                default => false,
            };

            return $unitAllowed && $accessAllowed;
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
