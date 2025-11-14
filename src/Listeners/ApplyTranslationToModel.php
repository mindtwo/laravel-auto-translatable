<?php

namespace Mindtwo\AutoTranslatable\Listeners;

use Exception;
use Illuminate\Support\Facades\Log;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;

class ApplyTranslationToModel
{
    public function __construct(
        protected TranslatableAdapter $adapter
    ) {}

    public function handle(ModelTranslationCompleted $event): void
    {
        if (! config('auto-translatable.auto_apply', false)) {
            return;
        }

        if (! $this->adapter->supports($event->model)) {
            return;
        }

        try {
            $this->adapter->applyTranslations($event->model, $event->results);
        } catch (Exception $e) {
            Log::error('Failed to auto-apply translations', [
                'model' => $event->model->getMorphClass(),
                'model_id' => $event->model->getKey(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
