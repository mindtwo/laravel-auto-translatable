<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Provider
    |--------------------------------------------------------------------------
    |
    | The AI provider to use for translations. Currently supported: anthropic
    |
    */

    'default_provider' => env('AUTO_TRANSLATABLE_PROVIDER', 'anthropic'),

    'providers' => [
        'anthropic' => [
            'driver' => \Mindtwo\AutoTranslatable\Services\Providers\AnthropicProvider::class,
            'model' => env('AUTO_TRANSLATABLE_ANTHROPIC_MODEL', 'claude-3-5-sonnet-20241022'),
            'max_tokens' => env('AUTO_TRANSLATABLE_ANTHROPIC_MAX_TOKENS', 16000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Locales
    |--------------------------------------------------------------------------
    */

    'default_source_locale' => env('AUTO_TRANSLATABLE_SOURCE_LOCALE', 'en'),

    'available_locales' => ['en', 'de', 'fr'],

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Maximum number of tokens per chunk when translating large content.
    |
    */

    'chunk_size' => env('AUTO_TRANSLATABLE_CHUNK_SIZE', 3000),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue_translations' => env('AUTO_TRANSLATABLE_QUEUE_ENABLED', true),

    'queue_connection' => env('AUTO_TRANSLATABLE_QUEUE_CONNECTION'),

    'queue_name' => env('AUTO_TRANSLATABLE_QUEUE_NAME', 'translations'),

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

    'adapter' => null,

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
