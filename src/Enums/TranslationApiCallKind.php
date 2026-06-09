<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Enums;

use Mindtwo\AutoTranslatable\Services\TranslationProvider;

/**
 * The shape of an underlying agent call made by {@see TranslationProvider}.
 *
 * Consumers that aggregate token usage (e.g. for cost reporting) typically
 * don't care about the kind — sum across calls regardless. The tag is exposed
 * so analytics and debug tooling can distinguish single-content prompts from
 * batched, structured-output requests.
 */
enum TranslationApiCallKind: string
{
    /**
     * A single-content call: one string of source content (possibly the
     * result of chunking a larger field) translated in one request.
     */
    case Chunk = 'chunk';

    /**
     * A batched structured-output call: multiple keyed fields translated in
     * one request via the structured agent.
     */
    case Fields = 'fields';
}
