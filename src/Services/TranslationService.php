<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationFailed;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

class TranslationService
{
    public function __construct(
        protected ChunkingStrategyResolver $strategyResolver,
        protected TranslationProvider $provider,
    ) {}

    /**
     * Translate a string directly.
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
                'provider' => config('auto-translatable.provider'),
                'model' => config('auto-translatable.model'),
            ]);
        } catch (Exception $e) {
            $result->markAsFailed($e->getMessage());

            throw $e;
        }

        return $result;
    }

    /**
     * Translate multiple fields on a model.
     *
     * @param array<string, string> $fields
     *
     * @return Collection<TranslationResult>
     */
    public function translateModel(
        Model $model,
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options = [],
    ): Collection {
        $results = collect();

        DB::transaction(function () use ($model, $fields, $results, $sourceLocale, $targetLocale, $options): void {
            foreach ($fields as $field => $content) {
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
                    // Get field-specific chunking strategy
                    $fieldOptions = $options;

                    if (isset($options['chunking_strategies'][$field])) {
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
                        'provider' => config('auto-translatable.provider').':'.config('auto-translatable.model'),
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
        });

        // Fire model translation completed event if we have results
        if ($results->isNotEmpty()) {
            event(new ModelTranslationCompleted($model, $results, $fields));
        }

        return $results;
    }

    /**
     * Perform the actual translation with chunking and post-processing.
     */
    public function performTranslation(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        TranslationResult $result,
        array $options,
    ): string {
        $result->markAsProcessing();

        $chunkSize = $options['chunk_size'] ?? config('auto-translatable.chunk_size', 80000);

        // Resolve chunking strategy (explicit or auto-detect)
        $strategyName = $options['chunking_strategy'] ?? 'auto';
        $strategy = $this->strategyResolver->resolve($content, $strategyName);

        $chunks = $strategy->chunk($content, $chunkSize);
        $result->update(['chunks_count' => count($chunks)]);

        // Translate each chunk
        $translatedChunks = [];

        foreach ($chunks as $chunk) {
            $translated = $this->provider->translateChunk($chunk, $sourceLocale, $targetLocale, $options);

            $translatedChunks[] = $translated;
        }

        // Combine chunks and apply post-processing
        $translatedContent = implode("\n\n", $translatedChunks);
        $postProcessors = $this->getPostProcessors($options);

        foreach ($postProcessors as $processor) {
            $translatedContent = $processor->process($translatedContent, $result);
        }

        return $translatedContent;
    }

    /**
     * Get post-processors to apply.
     *
     * @return array<PostProcessor>
     */
    protected function getPostProcessors(array $options): array
    {
        $processors = [];

        // Add link replacer if enabled
        if ($linkReplacer = app('auto-translatable.link-replacer')) {
            $processors[] = $linkReplacer;
        }

        // Add any custom processors from options
        foreach ($options['post_processors'] ?? [] as $processor) {
            if ($processor instanceof PostProcessor) {
                $processors[] = $processor;
            }
        }

        return $processors;
    }
}
