<?php

namespace Mindtwo\AutoTranslatable\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

interface TranslatableAdapter
{
    /**
     * Check if this adapter can handle the given model.
     */
    public function supports(Model $model): bool;

    /**
     * Get the configured locales for translation.
     *
     * @return array<string>
     */
    public function getAvailableLocales(Model $model): array;

    /**
     * Get the source locale for the model's content
     * This determines which locale the current content is in.
     */
    public function getSourceLocale(Model $model): string;

    /**
     * Get the value of a field in a specific locale.
     */
    public function getFieldValue(Model $model, string $field, string $locale): ?string;

    /**
     * Apply translation results to the model.
     *
     * @param  Collection<TranslationResult>  $results
     */
    public function applyTranslations(Model $model, Collection $results): void;
}
