# Changelog

All notable changes to `mindtwo/laravel-auto-translatable` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[0.2.0]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.4...0.2.0
[0.1.4]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/mindtwo/laravel-auto-translatable/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/mindtwo/laravel-auto-translatable/releases/tag/0.1.0
