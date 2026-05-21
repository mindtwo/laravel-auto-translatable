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
use Mindtwo\AutoTranslatable\Support\Config;

class TranslateContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** The number of seconds the job may run before timing out. */
    public int $timeout;

    /**
     * Create a new job instance.
     *
     * @param array<string, string> $fields attribute => source content
     * @param array<string, mixed> $options
     */
    public function __construct(
        public Model $model,
        public array $fields,
        public string $sourceLocale,
        public string $targetLocale,
        public array $options = [],
    ) {
        $connection = config('auto-translatable.queue_connection');

        $this->onConnection(is_string($connection) ? $connection : null);
        $this->onQueue(Config::string('auto-translatable.queue_name', 'translations'));
        $this->timeout = Config::int('auto-translatable.queue_timeout', 600);
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle(TranslationService $service, TranslatableAdapter $adapter): void
    {
        $service->translateModel($this->model, $this->fields, $this->sourceLocale, $this->targetLocale, $this->options);
    }
}
