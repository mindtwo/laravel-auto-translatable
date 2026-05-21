<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\PostProcessors;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Mindtwo\AutoTranslatable\Contracts\LinkMappingResolver;
use Mindtwo\AutoTranslatable\Contracts\PostProcessor;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Support\Config;

class LinkReplacer implements PostProcessor
{
    /** The configured link mapping resolver instance. */
    protected ?LinkMappingResolver $resolver = null;

    /**
     * The hosts that should be treated as internal.
     *
     * @var array<int, string>
     */
    protected array $internalHosts = [];

    /**
     * The static URL mapping cached for the current translation.
     *
     * @var array<string, string>
     */
    protected array $mappingCache = [];

    /**
     * Create a new link replacer and resolve its dependencies from config.
     */
    public function __construct()
    {
        $this->internalHosts = array_map(
            static fn (string $host): string => mb_rtrim($host, '/'),
            Config::stringList('auto-translatable.link_replacement.internal_hosts'),
        );

        $resolverClass = Config::string('auto-translatable.link_replacement.resolver');

        if ($resolverClass !== '') {
            $resolver = app($resolverClass);

            if ($resolver instanceof LinkMappingResolver) {
                $this->resolver = $resolver;
            }
        }
    }

    /**
     * Replace every markdown link with the locale-specific equivalent.
     *
     * @param array<string, mixed> $context
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    public function process(string $content, TranslationResult $result, array $context = []): string
    {
        // The static mapping is loaded once per translation to avoid repeated lookups.
        if ($this->resolver && empty($this->mappingCache)) {
            $this->mappingCache = $this->resolver->getMapping($result->source_locale, $result->target_locale);
        }

        $processed = preg_replace_callback(
            '/\[([^\]]+)\]\(([^)]+)\)/',
            function (array $matches) use ($result): string {
                $text = $matches[1];
                $url = $matches[2];

                if (! $this->isInternalLink($url)) {
                    return $matches[0];
                }

                $normalizedUrl = $this->normalizeUrl($url);
                $mappedUrl = $this->findMappedUrl($normalizedUrl, $url, $result);

                if ($mappedUrl !== null) {
                    return "[{$text}]({$mappedUrl})";
                }

                return $this->handleUnmappedLink($text, $url);
            },
            $content,
        );

        return $processed ?? $content;
    }

    /**
     * Determine if the given URL is internal.
     */
    protected function isInternalLink(string $url): bool
    {
        // Relative paths are always internal.
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        foreach ($this->internalHosts as $host) {
            if (str_starts_with($url, $host)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize the given URL to its relative path for mapping lookups.
     */
    protected function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        foreach ($this->internalHosts as $host) {
            if (str_starts_with($url, $host)) {
                return '/'.mb_ltrim(mb_substr($url, mb_strlen($host)), '/');
            }
        }

        return $url;
    }

    /**
     * Find the mapped URL for the given path.
     *
     * @throws BindingResolutionException
     * @throws CircularDependencyException
     */
    protected function findMappedUrl(string $normalizedUrl, string $originalUrl, TranslationResult $result): ?string
    {
        if (isset($this->mappingCache[$normalizedUrl])) {
            $mapped = $this->mappingCache[$normalizedUrl];

            if ($this->isFullUrl($originalUrl)) {
                return $this->toFullUrl($mapped, $result->target_locale);
            }

            return $mapped;
        }

        if ($this->resolver instanceof LinkMappingResolver) {
            $resolved = $this->resolver->resolve($normalizedUrl, $result->source_locale, $result->target_locale);

            if ($resolved !== null) {
                if ($this->isFullUrl($originalUrl)) {
                    return $this->toFullUrl($resolved, $result->target_locale);
                }

                return $resolved;
            }
        }

        return null;
    }

    /**
     * Determine if the given URL is fully qualified.
     */
    protected function isFullUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    /**
     * Convert a relative path into a fully qualified URL for the target locale.
     */
    protected function toFullUrl(string $path, string $locale): string
    {
        // Prefer an internal host that already encodes the target locale.
        foreach ($this->internalHosts as $host) {
            if (str_contains($host, ".{$locale}") || str_ends_with($host, "/{$locale}")) {
                return mb_rtrim($host, '/').'/'.mb_ltrim($path, '/');
            }
        }

        if (! empty($this->internalHosts)) {
            return mb_rtrim($this->internalHosts[0], '/').'/'.mb_ltrim($path, '/');
        }

        return $path;
    }

    /**
     * Decide how to render an unmapped internal link according to configuration.
     */
    protected function handleUnmappedLink(string $text, string $url): string
    {
        return match (Config::string('auto-translatable.link_replacement.unmapped_links', 'remove')) {
            'remove' => $text,
            'keep' => "[{$text}]({$url})",
            'warn' => "[{$text}]({$url})<!-- UNMAPPED: {$url} -->",
            default => $text,
        };
    }
}
