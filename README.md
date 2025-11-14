# Laravel Auto-Translatable

AI-powered translation package for Laravel with smart markdown chunking, extensible adapters, and automatic link replacement.

## Features

- 🤖 **AI-Powered Translations** using Anthropic Claude (via PRISM)
- 📝 **Smart Markdown Chunking** that respects syntax boundaries and never breaks mid-sentence
- 🔗 **Automatic Link Replacement** for internal links with configurable mappings
- 🎯 **Multiple Adapter Support** (Spatie Translatable, custom adapters)
- 🔄 **Event-Driven Architecture** for flexible post-processing workflows
- ⚡ **Queue Support** for async translations
- 🧩 **Extensible** with custom providers, adapters, and post-processors

## Installation

```bash
composer require mindtwo/laravel-auto-translatable
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=auto-translatable-config
php artisan vendor:publish --tag=auto-translatable-migrations
php artisan migrate
```

## Configuration

Configure the package in `config/auto-translatable.php`:

```php
return [
    'default_provider' => 'anthropic',
    'default_source_locale' => 'de',
    'chunk_size' => 3000,

    // Adapter for automatic storage
    'adapter' => \Mindtwo\AutoTranslatable\Adapters\NullAdapter::class,
    'auto_apply' => false,

    // Link replacement
    'link_replacement' => [
        'enabled' => false,
        'resolver' => \App\Services\MyLinkResolver::class,
        'internal_hosts' => ['https://example.com'],
        'unmapped_links' => 'remove',
    ],
];
```

Set your API key in `.env`:

```env
ANTHROPIC_API_KEY=your-api-key
```

## Basic Usage

### 1. Add Trait to Your Model

```php
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations;

class Post extends Model
{
    use HasAutoTranslations;

    public function autoTranslatableFields(): array
    {
        return ['title', 'body', 'excerpt'];
    }
}
```

### 2. Translate Content

```php
// Automatically translate all fields to all configured locales
$post->autoTranslate();

// The adapter determines:
// - Which locale the content is currently in (source)
// - Which locales to translate to (from config or Spatie config)
// - How to read field values (handles JSON columns, etc.)
// - How to store translations when complete
```

**How It Works:**

1. Call `$model->autoTranslate()` on any model with the trait
2. The configured adapter reads the source locale and available target locales
3. For each target locale, the adapter extracts field values from the source locale
4. Translation jobs are dispatched for each target locale
5. When complete, events fire that you can listen to
6. If `auto_apply` is enabled, the adapter automatically stores translations

### 3. Handle Results via Events

```php
// app/Listeners/HandleTranslationCompleted.php
use Mindtwo\AutoTranslatable\Events\TranslationCompleted;

class HandleTranslationCompleted
{
    public function handle(TranslationCompleted $event): void
    {
        $result = $event->result;
        $model = $event->model;

        // Store translation in your preferred way
        $model->update([
            "{$event->field}_en" => $result->translated_content,
        ]);
    }
}

// EventServiceProvider
protected $listen = [
    TranslationCompleted::class => [
        HandleTranslationCompleted::class,
    ],
];
```

## Integration with Spatie Translatable

If you're using [spatie/laravel-translatable](https://github.com/spatie/laravel-translatable):

```php
// config/auto-translatable.php
return [
    'adapter' => \Mindtwo\AutoTranslatable\Adapters\SpatieTranslatableAdapter::class,
    'auto_apply' => true, // Automatically apply translations to JSON columns
];

// Model
use Spatie\Translatable\HasTranslations;
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations as AiTranslatable;

class Post extends Model
{
    use HasTranslations, AiTranslatable;

    public $translatable = ['title', 'body'];

    public function autoTranslatableFields(): array
    {
        return $this->translatable;
    }
}

// Trigger translation
$post->autoTranslate();

// After job completes, translations are automatically stored in JSON columns:
$post->getTranslation('title', 'en'); // English title
$post->getTranslation('title', 'de'); // German title
$post->title; // Returns title in app()->getLocale()
```

## Link Replacement

### 1. Create a Link Mapping Resolver

```php
// app/Services/MyLinkResolver.php
namespace App\Services;

use Mindtwo\AutoTranslatable\Contracts\LinkMappingResolver;

class MyLinkResolver implements LinkMappingResolver
{
    public function getMapping(string $sourceLocale, string $targetLocale): array
    {
        return [
            '/blog/german-post' => '/blog/english-post',
            '/kontakt' => '/contact',
            '/ueber-uns' => '/about',
        ];
    }

    public function resolve(string $url, string $sourceLocale, string $targetLocale): ?string
    {
        // Dynamic resolution for URLs not in cached mapping
        // Return null if no mapping found
        return null;
    }
}
```

### 2. Configure Link Replacement

```php
// config/auto-translatable.php
return [
    'link_replacement' => [
        'enabled' => true,
        'resolver' => \App\Services\MyLinkResolver::class,
        'internal_hosts' => [
            'https://example.de',
            'https://www.example.de',
            'https://example.com',
            'https://www.example.com',
        ],
        'unmapped_links' => 'remove', // 'remove', 'keep', or 'warn'
    ],
];
```

### 3. How It Works

**Input (German):**
```markdown
Check out [our guide](/blog/german-post) and [contact us](/kontakt).
Also see [this german-only post](/blog/nur-deutsch).
```

**Output (English):**
```markdown
Check out [our guide](/blog/english-post) and [contact us](/contact).
Also see this german-only post.
```

## Advanced Usage

### Custom Post-Processors

```php
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;

class MyCustomProcessor implements PostProcessor
{
    public function process(string $content, TranslationResult $result, array $context = []): string
    {
        // Custom processing logic
        return $content;
    }
}

// Use in translation
$post->translate(['body'], 'en', options: [
    'post_processors' => [new MyCustomProcessor()],
]);
```

### Custom Adapters

```php
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;

class MyCustomAdapter implements TranslatableAdapter
{
    public function supports(Model $model): bool
    {
        return $model instanceof MyTranslatableModel;
    }

    public function applyTranslation(Model $model, TranslationResult $result, bool $save = true): void
    {
        // Your custom storage logic
    }

    // ... implement other methods
}

// Register in config
'adapter' => \App\Adapters\MyCustomAdapter::class,
```

### Direct Translation Service

```php
use Mindtwo\AutoTranslatable\Services\TranslationService;

$service = app(TranslationService::class);

// Translate a string
$result = $service->translate(
    'Hallo Welt',
    'de',
    'en'
);

echo $result->translated_content; // "Hello World"
```

## Events

The package dispatches the following events:

- `TranslationStarted` - When translation begins
- `TranslationCompleted` - When a single field translation completes
- `TranslationFailed` - When a translation fails
- `ModelTranslationCompleted` - When all fields of a model complete

## Testing

```bash
composer test
```

## Architecture

```
┌─────────────┐
│    Model    │
│ (HasTrans)  │
└──────┬──────┘
       │
       ├─→ TranslateContent (Job)
       │
       ├─→ TranslationService
       │   ├─→ MarkdownChunker
       │   ├─→ AnthropicProvider (PRISM)
       │   └─→ PostProcessors (LinkReplacer)
       │
       ├─→ TranslationResult (stored)
       │
       └─→ Events
           ├─→ TranslationCompleted
           └─→ ModelTranslationCompleted
                 │
                 └─→ ApplyTranslationToModel (Listener)
                     └─→ Adapter (Spatie, Custom, or Null)
```

## Requirements

- PHP 8.2+
- Laravel 11+
- Anthropic API key

## License

MIT

## Credits

- [mindtwo GmbH](https://mindtwo.de)
