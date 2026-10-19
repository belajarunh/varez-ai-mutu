<?php

namespace App\Services\Ai\Contracts;

interface TextGenerationProviderInterface
{
    public function name(): string;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(array $messages, array $options = []): array;

    public function isConfigured(): bool;

    /** @return array<string, mixed> */
    public function metadata(): array;
}
