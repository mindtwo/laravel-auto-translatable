<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Enums;

enum TranslationStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
