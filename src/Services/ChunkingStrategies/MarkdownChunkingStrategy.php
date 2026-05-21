<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\MarkdownChunker;

class MarkdownChunkingStrategy implements ChunkingStrategy
{
    /**
     * Create a new markdown chunking strategy.
     */
    public function __construct(
        private readonly MarkdownChunker $chunker,
    ) {}

    /**
     * Chunk markdown content while preserving structural boundaries.
     *
     * @return array<int, string>
     */
    public function chunk(string $content, int $maxTokens): array
    {
        return $this->chunker->chunk($content, $maxTokens);
    }

    /**
     * Determine if the content contains markdown syntax.
     */
    public function canHandle(string $content): bool
    {
        return (bool) preg_match(
            '/^#{1,6}\s|' // heading at start
            .'\n#{1,6}\s|' // heading after a newline
            .'```|' // code fence
            .'\*\*[^*]+\*\*|' // bold (asterisks)
            .'__[^_]+__|' // bold (underscores)
            .'\[.+\]\(.+\)/', // link
            $content,
        );
    }

    /**
     * Get the strategy identifier.
     */
    public function getName(): string
    {
        return 'markdown';
    }
}
