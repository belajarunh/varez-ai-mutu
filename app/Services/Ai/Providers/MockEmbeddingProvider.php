<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\EmbeddingProviderInterface;

class MockEmbeddingProvider implements EmbeddingProviderInterface
{
    public function name(): string
    {
        return 'mock';
    }

    public function embed(array $texts, array $options = []): array
    {
        $vectors = [];
        foreach ($texts as $index => $text) {
            $seed = (int) crc32(substr((string) $text, 0, 64).':'.$index);
            $vector = [];
            for ($i = 0; $i < ($options['dimensions'] ?? 1536); $i++) {
                $vector[] = (float) (($seed + $i) % 10) / 10;
            }
            $vectors[] = $vector;
        }

        return $vectors;
    }

    public function dimensions(): int
    {
        return 1536;
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
