<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ModelTranslationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $model,
        public Collection $results,
        public array $fields,
    ) {}
}
