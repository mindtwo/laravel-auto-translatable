<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\MarkdownChunker;

class MarkdownChunkingStrategy implements ChunkingStrategy
{
    public function __construct(
        private readonly MarkdownChunker $chunker,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function chunk(string $content, int $maxTokens): array
    {
        return $this->chunker->chunk($content, $maxTokens);
    }

    /**
     * {@inheritDoc}
     */
    public function canHandle(string $content): bool
    {
        return (bool) preg_match(
            '/^#{1,6}\s|' // Heading at start
            .'\n#{1,6}\s|' // Heading after newline
            .'```|' // Code fence
            .'\*\*[^*]+\*\*|' // Bold
            .'__[^_]+__|' // Bold (underscores)
            .'\[.+\]\(.+\)/', // Link
            $content,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'markdown';
    }
}
