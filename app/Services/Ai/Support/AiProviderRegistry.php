<?php

namespace App\Services\Ai\Support;

use App\Services\Ai\Contracts\EmbeddingProviderInterface;
use App\Services\Ai\Contracts\TextGenerationProviderInterface;
use InvalidArgumentException;

class AiProviderRegistry
{
    /** @var array<string, EmbeddingProviderInterface> */
    protected array $embeddingProviders = [];

    /** @var array<string, TextGenerationProviderInterface> */
    protected array $generationProviders = [];

    public function registerEmbedding(string $name, EmbeddingProviderInterface $provider): void
    {
        $this->embeddingProviders[$name] = $provider;
    }

    public function registerGeneration(string $name, TextGenerationProviderInterface $provider): void
    {
        $this->generationProviders[$name] = $provider;
    }

    public function getEmbedding(string $name): EmbeddingProviderInterface
    {
        if (! array_key_exists($name, $this->embeddingProviders)) {
            throw new InvalidArgumentException("Unknown embedding provider: {$name}");
        }

        return $this->embeddingProviders[$name];
    }

    public function getGeneration(string $name): TextGenerationProviderInterface
    {
        if (! array_key_exists($name, $this->generationProviders)) {
            throw new InvalidArgumentException("Unknown generation provider: {$name}");
        }

        return $this->generationProviders[$name];
    }
}
