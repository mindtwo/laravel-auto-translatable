<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

interface TranslatableAdapter
{
    /**
     * Determine if this adapter can handle the given model.
     */
    public function supports(Model $model): bool;

    /**
     * Get the locales that translations should be generated for.
     *
     * @return array<int, string>
     */
    public function getAvailableLocales(Model $model): array;

    /**
     * Get the source locale that the model's content is written in.
     */
    public function getSourceLocale(Model $model): string;

    /**
     * Get the value of the given attribute in the given locale.
     */
    public function getFieldValue(Model $model, string $field, string $locale): ?string;

    /**
     * Persist the completed translation results onto the model.
     *
     * @param Collection<int, TranslationResult> $results
     */
    public function applyTranslations(Model $model, Collection $results): void;
}
