<?php

namespace Mindtwo\AutoTranslatable\Contracts;

interface LinkMappingResolver
{
    /**
     * Get the mapping of source URLs to target URLs
     *
     * @return array<string, string> [sourceUrl => targetUrl]
     */
    public function getMapping(string $sourceLocale, string $targetLocale): array;

    /**
     * Resolve a single URL dynamically (optional fallback)
     * Return null if no mapping found
     */
    public function resolve(string $url, string $sourceLocale, string $targetLocale): ?string;
}
