<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Ai\Responses\Data\Usage;
use Mindtwo\AutoTranslatable\Enums\TranslationApiCallKind;

/**
 * Fired after every underlying agent call made by the package.
 *
 * One event per real API request, regardless of how many fields or
 * `TranslationResult` rows the request fed. Carries the full {@see Usage}
 * payload so consumers can attribute tokens (and therefore cost) at the
 * call level — which is the only level where per-call usage is unambiguous.
 *
 * For a batched structured-output call, $kind is {@see TranslationApiCallKind::Fields}
 * and $fieldKeys lists the keys covered by the request. For a single-content
 * chunk call (the chunked-fallback path), $kind is {@see TranslationApiCallKind::Chunk}
 * and $fieldKeys is empty.
 *
 * When the call originates from a model translation ({@see \Mindtwo\AutoTranslatable\Services\TranslationService::translateModel()}
 * / `HasAutoTranslations::autoTranslate()`), $translatable is the model being
 * translated so consumers can attribute the cost to a concrete subject. It is
 * null for direct, model-less calls (`translate()` / `translateMany()`).
 */
class TranslationApiCallCompleted
{
    use Dispatchable;

    /**
     * @param array<int, string> $fieldKeys list of field keys covered by the call (empty for chunk calls)
     */
    public function __construct(
        public TranslationApiCallKind $kind,
        public string $sourceLocale,
        public string $targetLocale,
        public Usage $usage,
        public ?string $provider,
        public ?string $model,
        public array $fieldKeys = [],
        public ?Model $translatable = null,
    ) {}
}
