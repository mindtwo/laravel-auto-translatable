<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Contracts;

interface LinkMappingResolver
{
    /**
     * Get the static mapping of source URLs to target URLs.
     *
     * @return array<string, string> sourceUrl => targetUrl
     */
    public function getMapping(string $sourceLocale, string $targetLocale): array;

    /**
     * Resolve a single URL dynamically.
     *
     * Returns null when no mapping is available for the given URL.
     */
    public function resolve(string $url, string $sourceLocale, string $targetLocale): ?string;
}
