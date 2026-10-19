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

    public function generate(array $messages, array $options = []): array
    {
        if ($this->apiKey === '') {
            throw new RuntimeException('OpenAI text generation provider is not configured.');
        }

        $text = collect($messages)->map(fn ($message) => $message['content'] ?? '')->implode("\n");

        return [
            'provider' => 'openai',
            'model' => $this->model,
            'content' => '[OpenAI provider placeholder] '.$text,
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ];
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
