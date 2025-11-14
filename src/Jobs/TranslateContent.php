<?php

namespace Mindtwo\AutoTranslatable\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationCompleted;
use Mindtwo\AutoTranslatable\Events\TranslationFailed;
use Mindtwo\AutoTranslatable\Events\TranslationStarted;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Services\TranslationService;

class TranslateContent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Model $model,
        public array $fields,
        public string $sourceLocale,
        public string $targetLocale,
        public array $options = []
    ) {
        // Set queue connection and name from config
        $this->onConnection(config('auto-translatable.queue_connection'));
        $this->onQueue(config('auto-translatable.queue_name', 'translations'));
    }

    /**
     * @throws Exception
     */
    public function handle(TranslationService $service, TranslatableAdapter $adapter): void
    {
        try {
            $results = collect();

            foreach ($this->fields as $field) {
                $content = $adapter->getFieldValue($this->model, $field, $this->sourceLocale);

                if (empty($content)) {
                    continue;
                }

                // Create translation result record
                $result = TranslationResult::query()->create([
                    'translatable_type' => $this->model->getMorphClass(),
                    'translatable_id' => $this->model->getKey(),
                    'field_name' => $field,
                    'source_locale' => $this->sourceLocale,
                    'target_locale' => $this->targetLocale,
                    'source_content' => $content,
                    'status' => TranslationStatus::PENDING,
                ]);

                event(new TranslationStarted($result, $this->model, $field));

                try {
                    $translatedContent = $service->performTranslation(
                        $content,
                        $this->sourceLocale,
                        $this->targetLocale,
                        $result,
                        $this->options
                    );

                    $result->markAsCompleted($translatedContent, [
                        'provider' => $service->getProviderClass(),
                        'model' => $this->model->getMorphClass(),
                        'model_id' => $this->model->getKey(),
                    ]);

                    event(new TranslationCompleted($result, $this->model, $field));

                    $results->push($result);
                } catch (Exception $e) {
                    $result->markAsFailed($e->getMessage());
                    event(new TranslationFailed($result, $e->getMessage(), $this->model, $field));
                    $results->push($result);
                }
            }

            // Fire model translation completed event if we have results
            if ($results->isNotEmpty()) {
                event(new ModelTranslationCompleted($this->model, $results, $this->fields));
            }
        } catch (Exception $e) {
            Log::error('Translation job failed', [
                'model' => $this->model->getMorphClass(),
                'model_id' => $this->model->getKey(),
                'fields' => $this->fields,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
