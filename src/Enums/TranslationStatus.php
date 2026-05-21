<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Enums;

/**
 * The lifecycle states of a translation result.
 */
enum TranslationStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case PROCESSING = 'processing';

    /*
     * The states below are reserved for downstream review workflows.
     * The package itself never transitions a result into them.
     */
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
