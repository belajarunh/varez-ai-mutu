<?php

namespace App\Services\Ai\Contracts;

interface EmbeddingProviderInterface
{
    public function name(): string;

    /**
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts, array $options = []): array;

    public function dimensions(): int;

    public function isConfigured(): bool;

    /** @return array<string, mixed> */
    public function metadata(): array;
}
