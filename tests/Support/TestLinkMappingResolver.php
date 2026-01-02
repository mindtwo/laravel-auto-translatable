<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests\Support;

use Mindtwo\AutoTranslatable\Contracts\LinkMappingResolver;

class TestLinkMappingResolver implements LinkMappingResolver
{
    public function getMapping(string $sourceLocale, string $targetLocale): array
    {
        return [
            '/docs/getting-started' => '/de/docs/erste-schritte',
            '/blog/hello-world' => '/de/blog/hallo-welt',
            '/products/laravel' => '/de/produkte/laravel',
        ];
    }

    public function resolve(string $url, string $sourceLocale, string $targetLocale): ?string
    {
        // Dynamic resolution for URLs not in the static mapping
        if (str_starts_with($url, '/api/')) {
            // API docs get locale prefix
            return "/{$targetLocale}{$url}";
        }

        return null;
    }
}
