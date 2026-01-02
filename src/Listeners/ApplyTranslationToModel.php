<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Listeners;

use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;

class ApplyTranslationToModel
{
    public function __construct(
        protected TranslatableAdapter $adapter,
    ) {}

    public function handle(ModelTranslationCompleted $event): void
    {
        if (! config('auto-translatable.auto_apply', false)) {
            return;
        }

        if (! $this->adapter->supports($event->model)) {
            return;
        }

        $this->adapter->applyTranslations($event->model, $event->results);
    }
}
