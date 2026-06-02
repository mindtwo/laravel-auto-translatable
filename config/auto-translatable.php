<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Adapters\SpatieTranslatableAdapter;

return [
    /*
    |--------------------------------------------------------------------------
    | AI Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which AI provider and model to use via laravel/ai.
    | Supported providers: anthropic, openai, gemini, etc. (any laravel/ai Lab)
    |
    | Credentials are configured in laravel/ai's own config/ai.php (e.g. via
    | ANTHROPIC_API_KEY), not here.
    |
    | request_timeout: Seconds to wait for a single translation request before
    |   timing out. Translating a full chunk can take a while, so this defaults
    |   to a generous value.
    |
    */

    'provider' => env('AUTO_TRANSLATABLE_PROVIDER', 'anthropic'),

    'model' => env('AUTO_TRANSLATABLE_MODEL', 'claude-sonnet-4-5'),

    'request_timeout' => env('AUTO_TRANSLATABLE_REQUEST_TIMEOUT', 500),

    /*
    |--------------------------------------------------------------------------
    | Token Configuration
    |--------------------------------------------------------------------------
    |
    | chunk_size: Maximum tokens per input chunk when translating large content
    |   - Should be 30-50% of total context window size of the model to leave room for output
    |   - Consider language verbosity (Chinese→German could expand 2x)
    |
    | output_tokens: Maximum tokens the model can generate in response
    |   - Must satisfy: chunk_size + output_tokens ≤ context_window
    |   - Should be ≥ chunk_size * 1.5 for verbose target languages
    |
    | Example calculations:
    |   - Conservative (handles Chinese→German): chunk=60k, output=120k, total=180k
    |   - Balanced (most language pairs): chunk=80k, output=100k, total=180k
    |   - Aggressive (German→English): chunk=100k, output=80k, total=180k
    |
    */

    'chunk_size' => env('AUTO_TRANSLATABLE_CHUNK_SIZE', 80000),

    'output_tokens' => env('AUTO_TRANSLATABLE_OUTPUT_TOKENS', 64000),

    /*
    |--------------------------------------------------------------------------
    | Batch Translation (Structured Output)
    |--------------------------------------------------------------------------
    |
    | When enabled, short attributes are translated together in a single
    | structured-output request per locale instead of one request per field.
    | This greatly reduces processing time for models with many small fields
    | (e.g. products) at the cost of per-field error isolation.
    |
    | Only fields that fit without chunking are eligible: a field is batched
    | when its token count is <= batch_max_tokens. Larger fields keep using the
    | per-field chunking path. If a batched request fails or omits a field, that
    | field is automatically retried on its own, so no data is lost.
    |
    | batch_max_fields caps how many fields go into one request; larger sets are
    | split across multiple requests to stay within the output token budget.
    |
    */

    'batch_fields' => env('AUTO_TRANSLATABLE_BATCH_ENABLED', false),

    'batch_max_tokens' => env('AUTO_TRANSLATABLE_BATCH_MAX_TOKENS', 1500),

    'batch_max_fields' => env('AUTO_TRANSLATABLE_BATCH_MAX_FIELDS', 50),

    /*
    |--------------------------------------------------------------------------
    | Default Locales
    |--------------------------------------------------------------------------
    */

    'default_source_locale' => env('AUTO_TRANSLATABLE_SOURCE_LOCALE', 'en'),

    'available_locales' => ['de', 'en', 'fr', 'es', 'it', 'pt', 'nl', 'pl'],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue_translations' => env('AUTO_TRANSLATABLE_QUEUE_ENABLED', true),

    'queue_connection' => env('AUTO_TRANSLATABLE_QUEUE_CONNECTION'),

    'queue_name' => env('AUTO_TRANSLATABLE_QUEUE_NAME', 'translations'),

    'queue_timeout' => env('AUTO_TRANSLATABLE_QUEUE_TIMEOUT', 600),

    /*
    |--------------------------------------------------------------------------
    | Link Replacement
    |--------------------------------------------------------------------------
    |
    | Configure link replacement for internal links during translation.
    |
    */

    'link_replacement' => [
        'enabled' => false,

        // Class implementing LinkMappingResolver interface
        'resolver' => null,

        // Internal hosts (URLs starting with these are considered internal)
        'internal_hosts' => [
            // Example: 'https://example.com', 'https://www.example.com',
        ],

        // Behavior for unmapped internal links: 'remove', 'keep', 'warn'
        'unmapped_links' => 'remove',
    ],

    /*
    |--------------------------------------------------------------------------
    | Translatable Adapter
    |--------------------------------------------------------------------------
    |
    | The adapter handles automatic storage of translations to your models.
    |
    | Available adapters:
    | - \Mindtwo\AutoTranslatable\Adapters\SpatieTranslatableAdapter::class
    |
    */

    'adapter' => SpatieTranslatableAdapter::class,

    /*
    |--------------------------------------------------------------------------
    | Auto Apply Translations
    |--------------------------------------------------------------------------
    |
    | When enabled, completed translations are automatically applied to models
    | via the configured adapter. Set to `false` to handle manually via events.
    |
    */

    'auto_apply' => env('AUTO_TRANSLATABLE_AUTO_APPLY', false),
];
