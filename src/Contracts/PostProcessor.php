<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Contracts;

use Mindtwo\AutoTranslatable\Models\TranslationResult;

interface PostProcessor
{
    /**
     * Process the translated content.
     */
    public function process(string $content, TranslationResult $result, array $context = []): string;
}
