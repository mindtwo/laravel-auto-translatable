<?php

namespace Mindtwo\AutoTranslatable\PostProcessors;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Mindtwo\AutoTranslatable\Contracts\LinkMappingResolver;
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

class LinkReplacer implements PostProcessor
{
    protected ?LinkMappingResolver $resolver = null;
    protected array $internalHosts = [];
    protected array $mappingCache = [];

    public function __construct()
    {
        $this->internalHosts = array_map(
            fn ($host) => rtrim($host, '/'),
            config('auto-translatable.link_replacement.internal_hosts')
        );

        if ($resolverClass = config('auto-translatable.link_replacement.resolver')) {
            $this->resolver = app($resolverClass);
        }
    }

    /**
     * Replace all Markdown links with their mapped equivalent in the target locale.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    public function process(string $content, TranslationResult $result, array $context = []): string
    {
        // Load mapping once per translation
        if ($this->resolver && empty($this->mappingCache)) {
            $this->mappingCache = $this->resolver->getMapping(
                $result->source_locale,
                $result->target_locale
            );
        }

        // Match all markdown links: [text](url)
        return preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function ($matches) use ($result) {
                $text = $matches[1];
                $url = $matches[2];

                // Check if this is an internal link
                if (! $this->isInternalLink($url)) {
                    return $matches[0]; // Keep external links as-is
                }

                // Normalize to relative path for mapping lookup
                $normalizedUrl = $this->normalizeUrl($url);

                // Try to find mapped URL
                $mappedUrl = $this->findMappedUrl($normalizedUrl, $url, $result);

                if ($mappedUrl !== null) {
                    return "[{$text}]({$mappedUrl})";
                }

                // Handle unmapped internal link
                return $this->handleUnmappedLink($text, $url);
            },
            $content
        );
    }

    /**
     * Check if the parsed URL is considered internal.
     */
    protected function isInternalLink(string $url): bool
    {
        // Relative paths are always internal
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        // Check if URL starts with any configured internal host
        foreach ($this->internalHosts as $host) {
            if (str_starts_with($url, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get relative path for a URL.
     */
    protected function normalizeUrl(string $url): string
    {
        // Already relative? Return as-is
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        // Convert full internal URL to relative path
        foreach ($this->internalHosts as $host) {
            if (str_starts_with($url, $host)) {
                return '/'.ltrim(substr($url, strlen($host)), '/');
            }
        }

        return $url;
    }

    /**
     * Find corresponding mapping for the given URL.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    protected function findMappedUrl(string $normalizedUrl, string $originalUrl, TranslationResult $result): ?string
    {
        // Direct mapping lookup
        if (isset($this->mappingCache[$normalizedUrl])) {
            $mapped = $this->mappingCache[$normalizedUrl];

            // If original was full URL, return full URL
            if ($this->isFullUrl($originalUrl)) {
                return $this->toFullUrl($mapped, $result->target_locale);
            }

            return $mapped;
        }

        // Try dynamic resolver
        if ($this->resolver) {
            $resolved = $this->resolver->resolve($normalizedUrl, $result->source_locale, $result->target_locale);

            if ($resolved !== null) {
                // If original was full URL, return full URL
                if ($this->isFullUrl($originalUrl)) {
                    return $this->toFullUrl($resolved, $result->target_locale);
                }

                return $resolved;
            }
        }

        return null;
    }

    /**
     * Check if the given URL is fully qualified.
     */
    protected function isFullUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Convert relative to full URL.
     */
    protected function toFullUrl(string $path, string $locale): string
    {
        // Get appropriate host for locale from internal hosts
        // Simple implementation - look for locale in domain
        foreach ($this->internalHosts as $host) {
            if (str_contains($host, ".{$locale}") || str_ends_with($host, "/{$locale}")) {
                return rtrim($host, '/').'/'.ltrim($path, '/');
            }
        }

        // Fallback to first internal host
        if (! empty($this->internalHosts)) {
            return rtrim($this->internalHosts[0], '/').'/'.ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Handle unmapped internal URL.
     */
    protected function handleUnmappedLink(string $text, string $url): string
    {
        return match (config('auto-translatable.link_replacement.unmapped_links')) {
            'remove' => $text, // Keep text, remove link
            'keep' => "[{$text}]({$url})", // Keep original link
            'warn' => "[{$text}]({$url})<!-- UNMAPPED: {$url} -->",
            default => $text,
        };
    }
}
