<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\TextGenerationProviderInterface;

class MockTextGenerationProvider implements TextGenerationProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function generate(array $messages, array $options = []): array
    {
        $prompt = $messages === []
            ? 'No prompt provided'
            : $messages[array_key_last($messages)]['content'];

        return [
            'provider' => 'mock',
            'model' => $options['model'] ?? 'mock-gpt',
            'content' => "[MOCK AI RESPONSE]\n\n{$prompt}",
            'usage' => [
                'prompt_tokens' => 0,
                'completion_tokens' => 0,
                'total_tokens' => 0,
            ],
        ];
    }

    public function isConfigured(): bool
    {
        return app()->environment('testing');
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return ['provider' => 'mock', 'mode' => 'testing'];
    }
}
