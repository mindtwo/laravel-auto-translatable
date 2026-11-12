# Upgrading

## Upgrading from 0.7 to 0.8

`0.8.0` moves to `laravel/ai` `^1.0`. Read the
[laravel/ai upgrade guide](https://github.com/laravel/ai/blob/main/UPGRADE.md) for
changes that affect your own use of laravel/ai (e.g. `laravel/mcp` must be `^1.0`,
and Bedrock now needs `aws/aws-sdk-php` installed directly).

```bash
composer update mindtwo/laravel-auto-translatable laravel/ai -W
```

### `TranslationApiCallCompleted` listeners

`$event->usage` is now a `Laravel\Ai\Responses\Data\TextUsage` and uses the
laravel/ai 1.0 token names:

```php
// Before...
$event->usage->promptTokens;
$event->usage->completionTokens;

// After...
$event->usage->inputTokens;
$event->usage->outputTokens;
```

The counts are now the provider's totals: `inputTokens` includes cache-read and
cache-written tokens, and `outputTokens` includes reasoning tokens. If you store
or price input tokens separately from cache tokens, use
`$event->usage->uncachedInputTokens()` for the uncached share. The optional
`cacheReadInputTokens`, `cacheWriteInputTokens` and `reasoningTokens` are `null`
when the provider does not report them.

### Tests that fake responses

If your tests build `TextResponse` / `StructuredTextResponse` objects for
`TranslationAgent::fake()` or `StructuredTranslationAgent::fake()`, pass a
`TextUsage` instead of a `Usage`. Note the argument order: cache **read** comes
before cache **write**.

```php
new TextUsage(inputTokens: 120, outputTokens: 30, cacheReadInputTokens: 80);
```

## Upgrading from 0.3.0 to 0.4.0

`0.4.0` is an **additive, non-breaking** release. The new batch-translation mode
is opt-in and disabled by default, so existing applications keep their current
per-field behavior with no changes.

### What you need to do

Nothing is required to upgrade. To adopt batch translation, set
`auto-translatable.batch_fields` to `true` (or `AUTO_TRANSLATABLE_BATCH_ENABLED=true`)
and optionally tune `batch_max_tokens` / `batch_max_fields`. See the
[Batch Translation](README.md#batch-translation-structured-output) section of the
README.

### One thing to be aware of

`TranslationService`'s constructor gained a third dependency,
`Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer`:

```php
public function __construct(
    protected ChunkingStrategyResolver $strategyResolver,
    protected TranslationProvider $provider,
    protected Tokenizer $tokenizer, // new in 0.4.0
) {}
```

If you resolve `TranslationService` from the container (the documented approach,
including constructor injection), this is handled automatically and **no change is
needed**. Only update your call site if you instantiate the service manually with
`new TranslationService(...)`.

## Upgrading from 0.2.0 to 0.3.0

`0.3.0` replaces the `prism-php/prism` AI backend with Laravel's first-party
[`laravel/ai`](https://github.com/laravel/ai) SDK. **This is a breaking release.**

### Platform requirements

- **PHP `^8.3`** (previously `^8.2`).
- **Laravel `^12.0 || ^13.0`** (Laravel 11 is no longer supported).

### What you need to do

1. **Remove PRISM (if you installed it directly)** and let Composer pull in
   `laravel/ai`:

   ```bash
   composer remove prism-php/prism   # only if you required it explicitly
   composer update mindtwo/laravel-auto-translatable
   ```

2. **Configure credentials in laravel/ai.** Credentials now live in laravel/ai's
   own `config/ai.php` instead of `config/prism.php`. Publish it and/or set the
   provider API key in your environment:

   ```bash
   php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
   ```

   ```ini
   ANTHROPIC_API_KEY=your-key-here
   ```

### What stays the same

- This package's config keys are **unchanged**:
  `provider`, `model`, `output_tokens`, `chunk_size`, and everything else in
  `config/auto-translatable.php`. Your existing `AUTO_TRANSLATABLE_PROVIDER` /
  `AUTO_TRANSLATABLE_MODEL` values (e.g. `anthropic` / `claude-sonnet-4-5`)
  continue to work — they now select a laravel/ai provider and model.
- The package's public API (`TranslationService`, `HasAutoTranslations`, events,
  `TranslationResult`, adapters) is **unchanged**. No code changes are required in
  your application beyond credential configuration.
