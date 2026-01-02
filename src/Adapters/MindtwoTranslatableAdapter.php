<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Adapters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;

class MindtwoTranslatableAdapter implements TranslatableAdapter
{
    /**
     * {@inheritDoc}
     */
    public function supports(Model $model): bool
    {
        return in_array(HasTranslations::class, class_uses_recursive($model), true);
    }

    /**
     * {@inheritDoc}
     */
    public function getAvailableLocales(Model $model): array
    {
        return config('auto-translatable.available_locales', []);
    }

    /**
     * {@inheritDoc}
     */
    public function getSourceLocale(Model $model): string
    {
        return $model->defaultLocaleOnModel();
    }

    /**
     * {@inheritDoc}
     */
    public function getFieldValue(Model $model, string $field, string $locale): ?string
    {
        if (! $this->supports($model)) {
            return null;
        }

        return $model->getTranslation($field, $locale);
    }

    /**
     * {@inheritDoc}
     */
    public function applyTranslations(Model $model, Collection $results): void
    {
        if (! $this->supports($model)) {
            throw new InvalidArgumentException('Model does not use Spatie HasTranslations trait');
        }

        foreach ($results as $result) {
            if (! $result->isCompleted()) {
                continue;
            }

            $fieldName = $result->field_name;

            if (! $fieldName) {
                continue;
            }

            $model->setTranslation($fieldName, $result->translated_content, $result->target_locale);
        }

        $model->save();
    }
}
