<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Concerns;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Jobs\TranslateContent;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use RuntimeException;

/**
 * @property Collection<int, TranslationResult> $translationResults
 * @property int|null $translation_results_count
 */
trait HasAutoTranslations
{
    /**
     * Define which fields are auto-translatable.
     *
     * @return array<string>
     */
    abstract public function autoTranslatableFields(): array;

    /**
     * Define chunking strategies for specific fields.
     *
     * Override this method to specify how different fields should be chunked:
     * - 'markdown': Markdown-aware chunking (preserves structure)
     * - 'plain': Plain text chunking (sentence-based)
     * - 'none': No chunking (single piece)
     * - 'auto': Auto-detect (default)
     *
     * @return array<string, string> Field name => strategy name
     */
    public function chunkingStrategies(): array
    {
        return [];
    }

    /**
     * @return MorphMany<TranslationResult, $this>
     */
    public function translationResults(): MorphMany
    {
        return $this->morphMany(TranslationResult::class, 'translatable');
    }

    /**
     * Get translation result for specific field/locale.
     */
    public function getTranslationResult(string $field, string $locale): ?TranslationResult
    {
        return $this->translationResults()
            ->where('field_name', '=', $field)
            ->where('target_locale', '=', $locale)
            ->where('status', '=', TranslationStatus::COMPLETED)
            ->latest()
            ->first();
    }

    /**
     * Automatically translate all translatable fields to all available locales.
     *
     * @param array<string> $options
     */
    public function autoTranslate(array $options = []): void
    {
        if (config('auto-translatable.adapter') === null) {
            throw new RuntimeException('No adapter configured for laravel-auto-translatable');
        }

        $adapter = app(TranslatableAdapter::class);
        $sourceLocale = $adapter->getSourceLocale($this);
        $availableLocales = $adapter->getAvailableLocales($this);

        // Filter out the source locale (don't translate to itself)
        $targetLocales = array_filter($availableLocales, fn (string $locale) => $locale !== $sourceLocale);

        // Get chunking strategies for fields
        $chunkingStrategies = $this->chunkingStrategies();

        // Translate to each target locale
        foreach ($targetLocales as $targetLocale) {
            $fieldsToTranslate = [];

            // Collect fields that have content in the source locale
            foreach ($this->autoTranslatableFields() as $field) {
                $sourceContent = $adapter->getFieldValue($this, $field, $sourceLocale);

                if (! empty($sourceContent)) {
                    $fieldsToTranslate[$field] = $sourceContent;
                }
            }

            // Skip if no fields to translate
            if (empty($fieldsToTranslate)) {
                continue;
            }

            // Merge chunking strategies into options
            $translationOptions = array_merge($options, [
                'chunking_strategies' => $chunkingStrategies,
            ]);

            // Dispatch translation job
            if (config('auto-translatable.queue_translations', true)) {
                TranslateContent::dispatch(
                    $this,
                    $fieldsToTranslate,
                    $sourceLocale,
                    $targetLocale,
                    $translationOptions,
                );
            } else {
                resolve(TranslationService::class)->translateModel(
                    $this,
                    $fieldsToTranslate,
                    $sourceLocale,
                    $targetLocale,
                    $translationOptions,
                );
            }
        }
    }

    public function hasPendingTranslationResult(string $field, string $locale): bool
    {
        return $this->translationResults()
            ->where('field_name', $field)
            ->where('target_locale', $locale)
            ->whereIn('status', [TranslationStatus::PENDING, TranslationStatus::PROCESSING])
            ->exists();
    }
}
