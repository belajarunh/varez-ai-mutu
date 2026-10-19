<?php

return [
    'default' => env('AI_DEFAULT_PROVIDER', 'mock'),

    'embedding' => [
        'provider' => env('AI_EMBEDDING_PROVIDER', 'mock'),
        'dimensions' => (int) env('AI_EMBEDDING_DIMENSIONS', 1536),
        'model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
    ],

    'generation' => [
        'provider' => env('AI_TEXT_PROVIDER', 'mock'),
        'model' => env('AI_TEXT_MODEL', 'gpt-4o-mini'),
    ],

    'providers' => [
        'mock' => [
            'enabled' => true,
            'label' => 'Mock provider',
        ],
        'openai' => [
            'enabled' => filled(env('OPENAI_API_KEY')),
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'embedding_model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'text_model' => env('AI_TEXT_MODEL', 'gpt-4o-mini'),
        ],
    ],

    'guardrails' => [
        'treat_documents_as_data' => true,
        'sanitize_dangerous_instructions' => true,
        'max_context_tokens' => 6000,
    ],
];
