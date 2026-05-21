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
     * Get the model attributes that should be auto-translated.
     *
     * @return array<int, string>
     */
    abstract public function autoTranslatableFields(): array;

    /**
     * Get the chunking strategy that should be used per attribute.
     *
     * Supported strategies:
     *   - "markdown": markdown aware chunking that preserves structure.
     *   - "plain":    plain text chunking that respects sentence boundaries.
     *   - "none":     pass the value through without chunking.
     *   - "auto":     detect the strategy from the content (default).
     *
     * @return array<string, string> attribute => strategy
     */
    public function chunkingStrategies(): array
    {
        return [];
    }

    /**
     * Get all translation results that belong to this model.
     *
     * @return MorphMany<TranslationResult, $this>
     */
    public function translationResults(): MorphMany
    {
        return $this->morphMany(TranslationResult::class, 'translatable');
    }

    /**
     * Get the latest completed translation result for the given attribute and locale.
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
     * Translate every translatable attribute into every configured locale.
     *
     * @param array<string, mixed> $options
     */
    public function autoTranslate(array $options = []): void
    {
        if (config('auto-translatable.adapter') === null) {
            throw new RuntimeException('No adapter configured for laravel-auto-translatable');
        }

        $adapter = app(TranslatableAdapter::class);
        $sourceLocale = $adapter->getSourceLocale($this);
        $availableLocales = $adapter->getAvailableLocales($this);

        // The source locale never needs to be translated into itself.
        $targetLocales = array_filter($availableLocales, fn (string $locale) => $locale !== $sourceLocale);

        $chunkingStrategies = $this->chunkingStrategies();

        foreach ($targetLocales as $targetLocale) {
            $fieldsToTranslate = [];

            // Only translate attributes that have content in the source locale.
            foreach ($this->autoTranslatableFields() as $field) {
                $sourceContent = $adapter->getFieldValue($this, $field, $sourceLocale);

                if (! empty($sourceContent)) {
                    $fieldsToTranslate[$field] = $sourceContent;
                }
            }

            if (empty($fieldsToTranslate)) {
                continue;
            }

            $translationOptions = array_merge($options, [
                'chunking_strategies' => $chunkingStrategies,
            ]);

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

    /**
     * Determine if a translation for the given attribute and locale is pending or processing.
     */
    public function hasPendingTranslationResult(string $field, string $locale): bool
    {
        return $this->translationResults()
            ->where('field_name', $field)
            ->where('target_locale', $locale)
            ->whereIn('status', [TranslationStatus::PENDING, TranslationStatus::PROCESSING])
            ->exists();
    }
}
