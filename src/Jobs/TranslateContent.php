<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Services\TranslationService;

class TranslateContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Model $model,
        public array $fields,
        public string $sourceLocale,
        public string $targetLocale,
        public array $options = [],
    ) {
        $this->onConnection(config('auto-translatable.queue_connection'));
        $this->onQueue(config('auto-translatable.queue_name', 'translations'));
    }

    /**
     * @throws Exception
     */
    public function handle(TranslationService $service, TranslatableAdapter $adapter): void
    {
        $service->translateModel($this->model, $this->fields, $this->sourceLocale, $this->targetLocale, $this->options);
    }
}
