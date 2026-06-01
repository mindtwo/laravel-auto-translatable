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
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Support\Config;

class TranslationService
{
    /**
     * Create a new translation service instance.
     */
    public function __construct(
        protected ChunkingStrategyResolver $strategyResolver,
        protected TranslationProvider $provider,
        protected Tokenizer $tokenizer,
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

            $result->markAsCompleted($translatedContent, $this->directMetadata());
        } catch (Exception $e) {
            $result->markAsFailed($e->getMessage());

            throw $e;
        }

        return $result;
    }

    /**
     * Translate several keyed strings in a single structured request.
     *
     * Each key is tracked by its own standalone TranslationResult. Keys the
     * model omits from the structured response are retried individually so a
     * partial response never loses data.
     *
     * @param array<string, string> $strings key => source content
     * @param array<string, mixed> $options
     *
     * @return Collection<string, TranslationResult>
     */
    public function translateMany(
        array $strings,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): Collection {
        /** @var Collection<string, TranslationResult> $results */
        $results = collect();

        // Cap how many strings go into a single structured request so a large
        // set is split across requests instead of overflowing the output-token
        // budget (which would fail the whole batch and fall back per field).
        $maxFields = max(1, Config::int('auto-translatable.batch_max_fields', 50));

        foreach (array_chunk($strings, $maxFields, true) as $group) {
            $pending = [];

            foreach ($group as $key => $content) {
                $pending[$key] = TranslationResult::query()->create([
                    'source_locale' => $sourceLocale,
                    'target_locale' => $targetLocale,
                    'source_content' => $content,
                    'status' => TranslationStatus::PENDING,
                ]);
            }

            $translated = $this->translateFieldsSafely($group, $sourceLocale, $targetLocale, $options);

            foreach ($group as $key => $content) {
                $result = $pending[$key];

                if (isset($translated[$key])) {
                    $result->markAsCompleted($translated[$key], $this->directMetadata() + ['batched' => true]);
                    $results[$key] = $result;

                    continue;
                }

                // The key was missing from the structured response (or the whole
                // call failed): translate it on its own as a fallback.
                try {
                    $translatedContent = $this->performTranslation(
                        $content,
                        $sourceLocale,
                        $targetLocale,
                        $result,
                        $options,
                    );
                    $result->markAsCompleted($translatedContent, $this->directMetadata());
                } catch (Exception $e) {
                    $result->markAsFailed($e->getMessage());
                }

                $results[$key] = $result;
            }
        }

        return $results;
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

        // The model is expected to use HasAutoTranslations; the trait method is
        // resolved dynamically and cannot be expressed in the type signature
        // without coupling the service to a concrete model.
        assert(method_exists($model, 'hasPendingTranslationResult'));

        // Drop attributes that already have a pending or processing result.
        $fieldsToTranslate = [];

        foreach ($fields as $field => $content) {
            if (! $model->hasPendingTranslationResult($field, $targetLocale)) {
                $fieldsToTranslate[$field] = $content;
            }
        }

        [$batchable, $individual] = $this->partitionFields($fieldsToTranslate);

        // Batching a single field only adds overhead and loses error isolation,
        // so it falls back to the per-field path.
        if (count($batchable) < 2) {
            $individual = $fieldsToTranslate;
            $batchable = [];
        }

        if ($batchable !== []) {
            $this->translateModelBatch($model, $batchable, $sourceLocale, $targetLocale, $options, $results);
        }

        foreach ($individual as $field => $content) {
            $result = $this->createModelResult($model, $field, $content, $sourceLocale, $targetLocale);
            $this->runFieldTranslation(
                $model,
                $field,
                $content,
                $sourceLocale,
                $targetLocale,
                $options,
                $result,
                $results,
            );
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
     * Split fields into those eligible for a single batched request and those
     * that must be translated individually.
     *
     * Batching is opt-in and only applies to fields small enough to translate
     * without chunking. Everything else stays on the per-field/chunking path.
     *
     * @param array<string, string> $fields
     *
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    protected function partitionFields(array $fields): array
    {
        if (! Config::bool('auto-translatable.batch_fields')) {
            return [[], $fields];
        }

        $maxTokens = Config::int('auto-translatable.batch_max_tokens', 1500);

        $batchable = [];
        $individual = [];

        foreach ($fields as $field => $content) {
            if ($this->tokenizer->count($content) <= $maxTokens) {
                $batchable[$field] = $content;
            } else {
                $individual[$field] = $content;
            }
        }

        return [$batchable, $individual];
    }

    /**
     * Translate batchable fields with one structured request per group, falling
     * back to per-field translation for any field the model omits.
     *
     * @param array<string, string> $fields
     * @param array<string, mixed> $options
     * @param Collection<int, TranslationResult> $results
     */
    protected function translateModelBatch(
        Model $model,
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options,
        Collection $results,
    ): void {
        $maxFields = max(1, Config::int('auto-translatable.batch_max_fields', 50));

        foreach (array_chunk($fields, $maxFields, true) as $group) {
            $pendingResults = [];

            foreach ($group as $field => $content) {
                $pendingResults[$field] = $this->createModelResult(
                    $model,
                    $field,
                    $content,
                    $sourceLocale,
                    $targetLocale,
                );
            }

            $translated = $this->translateFieldsSafely($group, $sourceLocale, $targetLocale, $options);

            foreach ($group as $field => $content) {
                $result = $pendingResults[$field];

                if (isset($translated[$field])) {
                    $result->markAsCompleted($translated[$field], $this->modelMetadata($model) + ['batched' => true]);
                    event(new TranslationCompleted($result, $model, $field));
                    $results->push($result);

                    continue;
                }

                // Missing from the structured response: recover this field with
                // a dedicated per-field translation, reusing its pending result.
                $this->runFieldTranslation(
                    $model,
                    $field,
                    $content,
                    $sourceLocale,
                    $targetLocale,
                    $options,
                    $result,
                    $results,
                );
            }
        }
    }

    /**
     * Translate a single model attribute and record the outcome.
     *
     * @param array<string, mixed> $options
     * @param Collection<int, TranslationResult> $results
     */
    protected function runFieldTranslation(
        Model $model,
        string $field,
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options,
        TranslationResult $result,
        Collection $results,
    ): void {
        try {
            $translatedContent = $this->performTranslation(
                $content,
                $sourceLocale,
                $targetLocale,
                $result,
                $this->fieldOptions($field, $options),
            );

            $result->markAsCompleted($translatedContent, $this->modelMetadata($model));

            event(new TranslationCompleted($result, $model, $field));

            $results->push($result);
        } catch (Exception $e) {
            $result->markAsFailed($e->getMessage());
            event(new TranslationFailed($result, $e->getMessage(), $model, $field));
            $results->push($result);
        }
    }

    /**
     * Translate fields through the provider, treating any failure as an empty
     * response so the caller can fall back per field.
     *
     * @param array<string, string> $fields
     * @param array<string, mixed> $options
     *
     * @return array<string, string>
     */
    protected function translateFieldsSafely(
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options,
    ): array {
        try {
            return $this->provider->translateFields($fields, $sourceLocale, $targetLocale, $options);
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Create a pending translation result for a model attribute.
     */
    protected function createModelResult(
        Model $model,
        string $field,
        string $content,
        string $sourceLocale,
        string $targetLocale,
    ): TranslationResult {
        return TranslationResult::query()->create([
            'translatable_type' => $model->getMorphClass(),
            'translatable_id' => $model->getKey(),
            'field_name' => $field,
            'source_locale' => $sourceLocale,
            'target_locale' => $targetLocale,
            'source_content' => $content,
            'status' => TranslationStatus::PENDING,
        ]);
    }

    /**
     * Merge the per-field chunking strategy into the translation options.
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    protected function fieldOptions(string $field, array $options): array
    {
        if (is_array($options['chunking_strategies'] ?? null) && isset($options['chunking_strategies'][$field])) {
            $options['chunking_strategy'] = $options['chunking_strategies'][$field];
        }

        return $options;
    }

    /**
     * Build the metadata stored for a model translation.
     *
     * @return array<string, mixed>
     */
    protected function modelMetadata(Model $model): array
    {
        return array_merge($this->directMetadata(), [
            'model' => $model->getMorphClass(),
            'model_id' => $model->getKey(),
        ]);
    }

    /**
     * Build the metadata stored for a direct (non-model) translation.
     *
     * @return array<string, mixed>
     */
    protected function directMetadata(): array
    {
        return [
            'provider' => Config::string('auto-translatable.provider').':'.Config::string('auto-translatable.model'),
        ];
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
