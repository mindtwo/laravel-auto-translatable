<?php

namespace Mindtwo\AutoTranslatable\Services;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;
use Mindtwo\AutoTranslatable\Contracts\TranslationProvider;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

class TranslationService
{
    public function __construct(
        protected TranslationProvider $provider,
        protected MarkdownChunker $chunker
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
        array $options = []
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
                $options
            );

            $result->markAsCompleted($translatedContent, [
                'provider' => get_class($this->provider),
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
     * @return Collection<TranslationResult>
     */
    public function translateModel(
        Model $model,
        array $fields,
        string $sourceLocale,
        string $targetLocale,
        array $options = []
    ): Collection {
        $results = collect();

        foreach ($fields as $field => $content) {
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
                $translatedContent = $this->performTranslation(
                    $content,
                    $sourceLocale,
                    $targetLocale,
                    $result,
                    $options
                );

                $result->markAsCompleted($translatedContent, [
                    'provider' => get_class($this->provider),
                ]);

                $results->push($result);
            } catch (Exception $e) {
                $result->markAsFailed($e->getMessage());
                $results->push($result);
            }
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
        array $options
    ): string {
        $result->markAsProcessing();

        // Get chunk size from options or config
        $chunkSize = $options['chunk_size'] ?? config('auto-translatable.chunk_size', 3000);

        // Chunk the content if needed
        $chunks = $this->chunker->chunk($content, $chunkSize);

        // Update chunks count
        $result->update(['chunks_count' => count($chunks)]);

        // Translate each chunk
        $translatedChunks = [];
        foreach ($chunks as $chunk) {
            $translated = $this->provider->translate(
                $chunk['content'],
                $sourceLocale,
                $targetLocale,
                $options
            );

            $translatedChunks[] = $translated;
        }

        // Combine translated chunks
        $translatedContent = implode("\n\n", $translatedChunks);

        // Apply post-processors
        $postProcessors = $this->getPostProcessors($options);
        foreach ($postProcessors as $processor) {
            $translatedContent = $processor->process($translatedContent, $result);
        }

        return $translatedContent;
    }

    public function getProviderClass(): string
    {
        return get_class($this->provider);
    }

    /**
     * Get post-processors to apply
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
