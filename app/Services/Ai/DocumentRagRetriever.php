<?php

namespace App\Services\Ai;

use App\Models\DocumentChunk;
use App\Models\Embedding;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class DocumentRagRetriever
{
    public function __construct(
        protected RetrievalGuardrails $guardrails,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function retrieve(int $institutionId, int $userId, string $query, int $limit = 5): array
    {
        $chunks = DocumentChunk::query()
            ->where('institution_id', $institutionId)
            ->where('index_status', 'ready')
            ->with('version')
            ->limit(200)
            ->get();

        $filtered = $this->guardrails->filterAccessible($chunks, $institutionId, $userId);
        $scored = $filtered->map(function (DocumentChunk $chunk) use ($query) {
            $score = 0.0;
            foreach (preg_split('/\s+/u', strtolower(trim($query))) as $term) {
                if ($term === '') {
                    continue;
                }
                if (str_contains(strtolower($chunk->content_text), $term)) {
                    $score += 1.0;
                }
            }

            return ['chunk' => $chunk, 'score' => $score];
        })->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        return $scored->map(function (array $item) {
            $chunk = $item['chunk'];
            $embedding = Embedding::query()->where('document_chunk_id', $chunk->getKey())->first();

            return [
                'chunk_id' => $chunk->getKey(),
                'document_version_id' => $chunk->document_version_id,
                'content' => $chunk->content_text,
                'score' => $item['score'],
                'section_title' => $chunk->section_title,
                'page_start' => $chunk->page_start,
                'page_end' => $chunk->page_end,
                'metadata' => $chunk->metadata_json ?? [],
                'embedding_dimensions' => $embedding?->dimensions ?? 0,
                'institution_id' => $chunk->institution_id,
            ];
        })->all();
    }
}
