<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Contracts;

/**
 * Strategy for chunking content for translation.
 *
 * Different content types (markdown, HTML, plain text) require different
 * chunking approaches to maintain semantic boundaries and context.
 */
interface ChunkingStrategy
{
    /**
     * Chunk content into smaller pieces suitable for translation.
     *
     * @param string $content The content to chunk
     * @param int $maxTokens Maximum tokens per chunk
     *
     * @return array<string> Array of content chunks
     */
    public function chunk(string $content, int $maxTokens): array;

    /**
     * Check if this strategy can handle the given content.
     *
     * Used for auto-detection of content type.
     *
     * @param string $content The content to check
     *
     * @return bool True if this strategy can handle the content
     */
    public function canHandle(string $content): bool;

    /**
     * Get the name/identifier for this strategy.
     *
     * @return string Strategy name (e.g., 'markdown', 'plain', 'html')
     */
    public function getName(): string;
}
