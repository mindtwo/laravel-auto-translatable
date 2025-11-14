<?php

namespace Mindtwo\AutoTranslatable\Enums;

enum TranslationStatus: string
{
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
}