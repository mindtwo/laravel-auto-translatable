<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

/**
 * Fired when every translatable attribute on a model has been processed.
 *
 * Individual results may have succeeded or failed; the listener decides how to react.
 */
class ModelTranslationCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Collection<int, TranslationResult> $results
     * @param array<string, string> $fields attribute => source content
     */
    public function __construct(
        public Model $model,
        public Collection $results,
        public array $fields,
    ) {}
}
