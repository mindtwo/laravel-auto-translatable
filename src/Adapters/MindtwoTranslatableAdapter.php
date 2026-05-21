<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Adapters;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Support\Config;
use mindtwo\LaravelTranslatable\Traits\HasTranslations;

class MindtwoTranslatableAdapter implements TranslatableAdapter
{
    /**
     * Determine if the model uses the mindtwo HasTranslations trait.
     */
    public function supports(Model $model): bool
    {
        return in_array(HasTranslations::class, class_uses_recursive($model), true);
    }

    /**
     * Get the locales that translations should be generated for.
     *
     * @return array<int, string>
     */
    public function getAvailableLocales(Model $model): array
    {
        return Config::stringList('auto-translatable.available_locales');
    }

    /**
     * Get the source locale that the model's content is written in.
     */
    public function getSourceLocale(Model $model): string
    {
        assert(method_exists($model, 'defaultLocaleOnModel'));

        $locale = $model->defaultLocaleOnModel();

        return is_scalar($locale) ? (string) $locale : '';
    }

    /**
     * Get the value of the given attribute in the given locale.
     */
    public function getFieldValue(Model $model, string $field, string $locale): ?string
    {
        if (! $this->supports($model)) {
            return null;
        }

        assert(method_exists($model, 'getTranslation'));

        $value = $model->getTranslation($field, $locale);

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * Persist the completed translation results onto the model.
     *
     * @param Collection<int, TranslationResult> $results
     */
    public function applyTranslations(Model $model, Collection $results): void
    {
        if (! $this->supports($model)) {
            throw new InvalidArgumentException('Model does not use mindtwo HasTranslations trait');
        }

        assert(method_exists($model, 'setTranslation'));

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
