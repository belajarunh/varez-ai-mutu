<?php

namespace App\Services\Ai;

use App\Services\Ai\Contracts\EmbeddingProviderInterface;
use App\Services\Ai\Contracts\TextGenerationProviderInterface;
use App\Services\Ai\Providers\MockEmbeddingProvider;
use App\Services\Ai\Providers\MockTextGenerationProvider;
use App\Services\Ai\Providers\OpenAiEmbeddingProvider;
use App\Services\Ai\Providers\OpenAiTextGenerationProvider;
use App\Services\Ai\Support\AiProviderRegistry;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderRegistry::class, function (): AiProviderRegistry {
            $registry = new AiProviderRegistry;
            $embeddingProvider = config('ai.embedding.provider');
            $generationProvider = config('ai.generation.provider');

            $registry->registerEmbedding('mock', new MockEmbeddingProvider);
            $registry->registerGeneration('mock', new MockTextGenerationProvider);

            $registry->registerEmbedding('openai', new OpenAiEmbeddingProvider(
                (string) config('ai.providers.openai.api_key', ''),
                (string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'),
                (string) config('ai.embedding.model', 'text-embedding-3-small'),
                (int) config('ai.embedding.dimensions', 1536),
            ));
            $registry->registerGeneration('openai', new OpenAiTextGenerationProvider(
                (string) config('ai.providers.openai.api_key', ''),
                (string) config('ai.providers.openai.base_url', 'https://api.openai.com/v1'),
                (string) config('ai.generation.model', 'gpt-4o-mini'),
            ));

            if ($embeddingProvider !== 'mock' && ! $registry->getEmbedding($embeddingProvider)->isConfigured()) {
                $registry->registerEmbedding($embeddingProvider, new MockEmbeddingProvider);
            }

            if ($generationProvider !== 'mock' && ! $registry->getGeneration($generationProvider)->isConfigured()) {
                $registry->registerGeneration($generationProvider, new MockTextGenerationProvider);
            }

            return $registry;
        });

        $this->app->bind(EmbeddingProviderInterface::class, function ($app): EmbeddingProviderInterface {
            $provider = config('ai.embedding.provider', 'mock');

            return $app->make(AiProviderRegistry::class)->getEmbedding($provider);
        });

        $this->app->bind(TextGenerationProviderInterface::class, function ($app): TextGenerationProviderInterface {
            $provider = config('ai.generation.provider', 'mock');

            return $app->make(AiProviderRegistry::class)->getGeneration($provider);
        });
    }
}
