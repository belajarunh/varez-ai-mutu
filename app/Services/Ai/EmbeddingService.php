<?php

namespace App\Services\Ai;

use App\Models\DocumentChunk;
use App\Models\Embedding;
use App\Services\Ai\Contracts\EmbeddingProviderInterface;
use RuntimeException;

class EmbeddingService
{
    public function __construct(
        protected EmbeddingProviderInterface $provider,
    ) {}

    /**
     * @param  list<DocumentChunk>  $chunks
     * @return list<array<string, mixed>>
     */
    public function generateForChunks(array $chunks): array
    {
        if (! $this->provider->isConfigured()) {
            throw new RuntimeException('Embedding provider is not configured.');
        }

        $texts = array_map(fn (DocumentChunk $chunk) => $chunk->content_text, $chunks);
        $vectors = $this->provider->embed($texts, ['dimensions' => config('ai.embedding.dimensions', 1536)]);
        $results = [];

        foreach ($chunks as $index => $chunk) {
            $vector = $vectors[$index] ?? [];
            $checksum = hash('sha256', json_encode($vector));
            $embedding = Embedding::query()->updateOrCreate(
                ['document_chunk_id' => $chunk->getKey(), 'provider' => $this->provider->name(), 'model' => config('ai.embedding.model', 'text-embedding-3-small')],
                ['institution_id' => $chunk->institution_id, 'dimensions' => count($vector), 'vector_json' => $vector, 'vector_checksum' => $checksum],
            );

            $results[] = [
                'id' => $embedding->getKey(),
                'chunk_id' => $chunk->getKey(),
                'dimensions' => count($vector),
                'checksum' => $checksum,
            ];
        }

        return $results;
    }

    public function validateCompatibility(int $modelDimensions): bool
    {
        return $modelDimensions === $this->provider->dimensions();
    }
}
