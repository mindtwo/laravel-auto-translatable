<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Contracts;

/**
 * Strategy for chunking content into LLM-sized pieces.
 *
 * Different content types (markdown, HTML, plain text) need different
 * chunking approaches in order to maintain semantic boundaries and context.
 */
interface ChunkingStrategy
{
    /**
     * Chunk the content into pieces that fit within the token budget.
     *
     * @return array<int, string>
     */
    public function chunk(string $content, int $maxTokens): array;

    /**
     * Determine if this strategy can handle the given content.
     *
     * Used by the resolver to auto-detect the right strategy for a payload.
     */
    public function canHandle(string $content): bool;

    /**
     * Get the strategy identifier (e.g. "markdown", "plain", "none").
     */
    public function getName(): string;
}
