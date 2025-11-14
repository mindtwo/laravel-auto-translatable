<?php

namespace Mindtwo\AutoTranslatable\Contracts;

interface TranslationProvider
{
    /**
     * Translate content from source locale to target locale
     */
    public function translate(
        string $content,
        string $sourceLocale,
        string $targetLocale,
        array $options = []
    ): string;

    /**
     * Estimate the number of tokens in the content
     */
    public function estimateTokens(string $content): int;

    /**
     * Get the maximum tokens this provider supports
     */
    public function getMaxTokens(): int;
}
