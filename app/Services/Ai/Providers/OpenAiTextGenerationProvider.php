<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\TextGenerationProviderInterface;
use RuntimeException;

class OpenAiTextGenerationProvider implements TextGenerationProviderInterface
{
    public function __construct(
        protected string $apiKey,
        protected string $baseUrl,
        protected string $model,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(array $messages, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI text generation provider is not configured.');
        }

        throw new RuntimeException('OpenAI text generation is not implemented; no provider request was sent.');
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
        ];
    }
}
