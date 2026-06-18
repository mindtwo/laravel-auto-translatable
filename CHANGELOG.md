# Changelog

All notable changes to `mindtwo/laravel-auto-translatable` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.7.0] — 2026-06-18

### Added
- **Translated model on the usage event.** `TranslationApiCallCompleted` now carries the model being translated as `$event->translatable` (`?Model`). When a call originates from a model translation (`TranslationService::translateModel()` / `HasAutoTranslations::autoTranslate()`), the event names the concrete subject so consumers can attribute token usage and cost to it directly — locale alone was ambiguous. The model is threaded through `TranslationProvider::translateChunk()` / `translateFields()` (new optional trailing `?Model $translatable = null` parameter) and the corresponding `TranslationService` internals.

### Notes
- Purely additive and backwards-compatible: the new parameter and event property both default to `null`, so direct, model-less calls (`translate()` / `translateMany()`) and any existing `TranslationApiCallCompleted` listeners behave identically.

## [0.6.0] — 2026-06-17

### Added
- **Per-call target locales.** `HasAutoTranslations::autoTranslate()` now accepts an explicit `locales` option (`autoTranslate(['locales' => ['fr', 'nl']])`) to translate into exactly that subset instead of every configured locale. The source locale is still always excluded. This makes on-demand, user-triggered translation, retrying a single failed locale, and incrementally adding a locale first-class — the per-locale primitive previously only existed at the lower-level `TranslationService::translateModel()`. When the option is omitted (or empty / not a list of strings), behaviour is unchanged: the adapter's configured available locales are used.

### Notes
- Purely additive and backwards-compatible: existing callers that don't pass `locales` behave identically. The `locales` key is treated as control-only and is not forwarded into the translation/provider options.

## [0.5.0] — 2026-06-09

### Added
- **Per-call usage tracking.** A new `Mindtwo\AutoTranslatable\Events\TranslationApiCallCompleted` event is dispatched after every underlying agent call made by `TranslationProvider` — both single-content chunk calls and batched structured-output calls. The event carries the full `Laravel\Ai\Responses\Data\Usage` payload (prompt tokens, completion tokens, cache read/write tokens), the provider/model identity, the source/target locales, and the list of field keys covered by the call. This gives consumers an unambiguous, per-call signal for cost reporting and usage analytics without having to attribute tokens to individual `TranslationResult` rows (which is fundamentally ambiguous for batched calls).
- New enum `Mindtwo\AutoTranslatable\Enums\TranslationApiCallKind` (`Chunk`, `Fields`) tagging the call shape on the event.

### Notes
- Purely additive: no API or behavior change to existing callers. The new event has no default listeners; consumers opt in by registering their own.

## [0.4.0] — 2026-06-03

### Added
- **Batch translation via structured output.** With the new opt-in `auto-translatable.batch_fields` flag, short attributes of a model are translated together in a single structured-output request per locale instead of one request per field, collapsing N fields × M locales from N×M requests to M. Built on a new `StructuredTranslationAgent` (implementing `laravel/ai`'s `HasStructuredOutput`) and `TranslationProvider::translateFields()`.
- `TranslationService::translateMany()` for translating an arbitrary keyed set of strings in a single batched request without a model.
- Three config keys: `batch_fields` (`AUTO_TRANSLATABLE_BATCH_ENABLED`, default `false`), `batch_max_tokens` (`AUTO_TRANSLATABLE_BATCH_MAX_TOKENS`, default `1500`) controlling which fields are small enough to batch, and `batch_max_fields` (`AUTO_TRANSLATABLE_BATCH_MAX_FIELDS`, default `50`) capping fields per request.

### Changed
- `TranslationService`'s constructor now also requires a `Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer`. This is resolved automatically by the service container; no change is needed unless you instantiate the service manually. See [UPGRADING.md](UPGRADING.md).

### Notes
- Batch mode is **off by default**; existing per-field behavior is byte-identical when disabled. Batching and chunking are mutually exclusive per field — only fields at or below `batch_max_tokens` are batched, while larger fields keep using the per-field chunking path.
- Batching is **resilient**: if a batched request fails or the model omits a field, the affected fields are automatically retried individually, so no data is lost. Per-field `TranslationResult` records, events, and adapters behave exactly as before (batched results carry `metadata.batched = true`).

## [0.3.0] — 2026-06-01

### Added
- Configurable per-request timeout via `auto-translatable.request_timeout` (`AUTO_TRANSLATABLE_REQUEST_TIMEOUT`), defaulting to the previous hard-coded 500 seconds.

### Changed
- **BREAKING:** Replaced the `prism-php/prism` AI backend with Laravel's first-party `laravel/ai` (`^0.7`). Translations now run through a `TranslationAgent` built on `Laravel\Ai`. See [UPGRADING.md](UPGRADING.md).
- **BREAKING:** Raised the minimum PHP version to `^8.3` and dropped Laravel 11 support (`illuminate/* ^12.0||^13.0`), matching laravel/ai's requirements.

### Notes
- The package's own config keys (`provider`, `model`, `output_tokens`, …) and public API are unchanged. Consumers must configure provider credentials in laravel/ai's `config/ai.php` (e.g. `ANTHROPIC_API_KEY`) instead of `config/prism.php`.

## [0.2.0] — 2026-05-21

### Added
- Laravel 13 support. The package now resolves on Laravel 11, 12, and 13 (`illuminate/* ^11.0||^12.0||^13.0`).
- `phpstan/phpstan` and `larastan/larastan` as development dependencies. A new `phpstan.neon.dist` enforces level `max` on `src/`.
- `Mindtwo\AutoTranslatable\Support\Config` helper that returns type-safe scalar and array values from the package configuration.
- `composer` script aliases for the quality gates: `composer test`, `composer analyse`, `composer lint`, and `composer lint:check`.

### Changed
- Widened `prism-php/prism` to `^0.99.20||^0.100.0`. The new minimum picked up Laravel 13 support; the upper bound covers the latest minor.
- Widened `orchestra/testbench` (dev) to `^10.0||^11.0` so the matrix can pick the right testbench per Laravel version.
- Pinned `phpro/grumphp` to `^2.0` instead of the floating `v2.x-dev` constraint.
- Rewrote every public docblock in `src/` to match Laravel core's terse, third-person present-tense style.
- Tightened parameter and return type annotations on the translatable adapters, services, jobs, events, and contracts so PHPStan can verify them at level `max`.

### Fixed
- `MindtwoTranslatableAdapter::applyTranslations()` now raises an `InvalidArgumentException` that references the mindtwo trait rather than the Spatie trait.

### Notes
- The package's `php` constraint remains `^8.2`. Composer's solver continues to enforce PHP `^8.3` transitively whenever Laravel 13 is selected.

## [0.1.4] — 2026-03-15

### Changed
- Removed the database transaction wrapper around the translation pipeline; individual translation results are created and updated independently, so the wrapper added no atomicity guarantees.

## [0.1.3] — 2026-02-10

### Changed
- Increased the PRISM client timeout to accommodate slower providers.

## [0.1.2] — 2026-02-03

### Fixed
- Fixed an issue where translated output was occasionally truncated.

## [0.1.1] — 2026-01-05

### Changed
- Updated package dependencies.

## [0.1.0] — 2026-01-02

### Added
- Initial public release: AI-translation pipeline with markdown-aware chunking, plain-text and pass-through strategies, automatic link replacement, Spatie and mindtwo translatable adapters, and queueable translation jobs.

[0.4.0]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.4...0.2.0
[0.1.4]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/mindtwo/laravel-auto-translatable/releases/tag/0.1.0
