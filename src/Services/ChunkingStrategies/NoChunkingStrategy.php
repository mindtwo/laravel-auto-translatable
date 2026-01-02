<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;

class NoChunkingStrategy implements ChunkingStrategy
{
    /**
     * {@inheritDoc}
     */
    public function chunk(string $content, int $maxTokens): array
    {
        return [$content];
    }

    /**
     * {@inheritDoc}
     */
    public function canHandle(string $content): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'none';
    }
}
