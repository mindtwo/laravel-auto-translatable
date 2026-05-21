<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;

class NoChunkingStrategy implements ChunkingStrategy
{
    /**
     * Return the content as a single chunk.
     *
     * @return array<int, string>
     */
    public function chunk(string $content, int $maxTokens): array
    {
        return [$content];
    }

    /**
     * Determine if this strategy can handle the given content.
     *
     * The pass-through strategy is opt-in and never matches during auto-detection.
     */
    public function canHandle(string $content): bool
    {
        return false;
    }

    /**
     * Get the strategy identifier.
     */
    public function getName(): string
    {
        return 'none';
    }
}
