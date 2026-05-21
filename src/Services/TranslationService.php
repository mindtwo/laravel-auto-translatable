<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationFailed;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Support\Config;

class TranslationService
{
    /**
     * Create a new translation service instance.
     */
    public function __construct(
        protected ChunkingStrategyResolver $strategyResolver,
        protected TranslationProvider $provider,
    ) {}

    /**
     * Translate a single string into the target locale.
     *
     * @param array<string, mixed> $options
     *
     * @throws Exception
     */
    public function translate(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): TranslationResult {
        $result = TranslationResult::query()->create([
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'source_content' => $content,
            'status' => TranslationStatus::PENDING,
        ]);

        try {
            $translatedContent = $this->performTranslation(
                $content,
                $sourceLocale,
                $targetLocale,
                $result,
                $options,
            );

            $result->markAsCompleted($translatedContent, [
                'provider' => Config::string('auto-translatable.provider'),
                'model' => Config::string('auto-translatable.model'),
            ]);
        } catch (Exception $e) {
            $result->markAsFailed($e->getMessage());

            throw $e;
        }

        return $result;
    }

    /**
     * Translate the given attributes for the model.
     *
     * @param array<string, string> $fields attribute => source content
     * @param array<string, mixed> $options
     *
     * @return Collection<int, TranslationResult>
     */
    public function translateModel(
        Model $model,
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): Collection {
        /** @var Collection<int, TranslationResult> $results */
        $results = collect();

        foreach ($fields as $field => $content) {
            // The model is expected to use HasAutoTranslations; the trait
            // method is resolved dynamically and cannot be expressed in the
            // type signature without coupling the service to a concrete model.
            assert(method_exists($model, 'hasPendingTranslationResult'));

            if ($model->hasPendingTranslationResult($field, $targetLocale)) {
                continue;
            }

            $result = TranslationResult::query()->create([
                'translatable_type' => $model->getMorphClass(),
                'translatable_id' => $model->getKey(),
                'field_name' => $field,
                'source_locale' => $sourceLocale,
                'target_locale' => $targetLocale,
                'source_content' => $content,
                'status' => TranslationStatus::PENDING,
            ]);

            try {
                $fieldOptions = $options;

                if (is_array(
                    $options['chunking_strategies'] ?? null,
                ) && isset($options['chunking_strategies'][$field])) {
                    $fieldOptions['chunking_strategy'] = $options['chunking_strategies'][$field];
                }

                $translatedContent = $this->performTranslation(
                    $content,
                    $sourceLocale,
                    $targetLocale,
                    $result,
                    $fieldOptions,
                );

                $result->markAsCompleted($translatedContent, [
                    'provider' => Config::string('auto-translatable.provider').':'.Config::string(
                        'auto-translatable.model',
                    ),
                    'model' => $model->getMorphClass(),
                    'model_id' => $model->getKey(),
                ]);

                event(new TranslationCompleted($result, $model, $field));

                $results->push($result);
            } catch (Exception $e) {
                $result->markAsFailed($e->getMessage());
                event(new TranslationFailed($result, $e->getMessage(), $model, $field));
                $results->push($result);
            }
        }

        if ($results->isNotEmpty()) {
            event(new ModelTranslationCompleted($model, $results, $fields));
        }

        return $results;
    }

    /**
     * Run the translation pipeline: chunk the content, translate each chunk, then post-process the result.
     *
     * @param array<string, mixed> $options
     */
    public function performTranslation(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        TranslationResult $result,
        array $options,
    ): string {
        $result->markAsProcessing();

        $chunkSize = is_numeric($options['chunk_size'] ?? null)
            ? (int) $options['chunk_size']
            : Config::int('auto-translatable.chunk_size', 80000);

        // Resolve the chunking strategy. An explicit name overrides auto-detection.
        $strategyName = $options['chunking_strategy'] ?? 'auto';
        $strategy = $this->strategyResolver->resolve($content, is_string($strategyName) ? $strategyName : null);

        $chunks = $strategy->chunk($content, $chunkSize);
        $result->update(['chunks_count' => count($chunks)]);

        $translatedChunks = [];

        foreach ($chunks as $chunk) {
            $translated = $this->provider->translateChunk($chunk, $sourceLocale, $targetLocale, $options);

            $translatedChunks[] = $translated;
        }

        $translatedContent = implode("\n\n", $translatedChunks);
        $postProcessors = $this->getPostProcessors($options);

        foreach ($postProcessors as $processor) {
            $translatedContent = $processor->process($translatedContent, $result);
        }

        return $translatedContent;
    }

    /**
     * Get the post-processors that should run for this translation.
     *
     * @param array<string, mixed> $options
     *
     * @return array<int, PostProcessor>
     */
    protected function getPostProcessors(array $options): array
    {
        $processors = [];

        $linkReplacer = app('auto-translatable.link-replacer');

        if ($linkReplacer instanceof PostProcessor) {
            $processors[] = $linkReplacer;
        }

        $custom = $options['post_processors'] ?? [];

        if (is_array($custom)) {
            foreach ($custom as $processor) {
                if ($processor instanceof PostProcessor) {
                    $processors[] = $processor;
                }
            }
        }

        return $processors;
    }
}
