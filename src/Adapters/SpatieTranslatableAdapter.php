<?php

namespace Mindtwo\AutoTranslatable\Adapters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Spatie\Translatable\HasTranslations;

class SpatieTranslatableAdapter implements TranslatableAdapter
{
    /**
     * @inheritDoc
     */
    public function supports(Model $model): bool
    {
        return in_array(HasTranslations::class, class_uses_recursive($model));
    }

    /**
     * @inheritDoc
     */
    public function getAvailableLocales(Model $model): array
    {
        return config('auto-translatable.available_locales', []);
    }

    /**
     * @inheritDoc
     */
    public function getSourceLocale(Model $model): string
    {
        return config('auto-translatable.default_source_locale', 'en');
    }

    /**
     * @inheritDoc
     */
    public function getFieldValue(Model $model, string $field, string $locale): ?string
    {
        if (! $this->supports($model)) {
            return null;
        }

        if (! $model->isTranslatableAttribute($field)) {
            return null;
        }

        return $model->getTranslation($field, $locale, false);
    }

    /**
     * @inheritDoc
     */
    public function applyTranslations(Model $model, Collection $results): void {
        if (! $this->supports($model)) {
            throw new InvalidArgumentException('Model does not use Spatie HasTranslations trait');
        }

        foreach ($results as $result) {
            if (! $result->isCompleted()) {
                continue;
            }

            $fieldName = $result->field_name;

            if (! $fieldName || ! $model->isTranslatableAttribute($fieldName)) {
                continue;
            }

            $model->setTranslation($fieldName, $result->target_locale, $result->translated_content);
        }

        $model->save();
    }
}
