<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

interface Tokenizer
{
    /**
     * Count the number of tokens in the given text.
     */
    public function count(string $text): int;
}
