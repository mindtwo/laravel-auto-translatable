# Upgrading

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
