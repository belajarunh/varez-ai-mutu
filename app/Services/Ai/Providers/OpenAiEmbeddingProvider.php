<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\EmbeddingProviderInterface;
use RuntimeException;

class OpenAiEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $model,
        protected int $dimensions,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function embed(array $texts, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI embedding provider is not configured.');
        }

        throw new RuntimeException('OpenAI embeddings are not implemented; no provider request was sent.');
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'provider' => 'openai',
            'base_url' => $this->baseUrl,
            'model' => $this->model,
            'dimensions' => $this->dimensions,
        ];
    }
}
