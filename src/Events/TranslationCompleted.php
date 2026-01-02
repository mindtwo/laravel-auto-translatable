<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

class TranslationCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public TranslationResult $result,
        public ?Model $model = null,
        public ?string $field = null,
    ) {}
}
