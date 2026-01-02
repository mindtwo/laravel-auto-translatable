<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

/**
 * Represents a node in the markdown document tree.
 *
 * Can be either a leaf node (text, code, table) or a heading with children.
 */
class MarkdownNode
{
    /**
     * @param int $tokenCount Token count for this node only (not including children)
     * @param string $raw Raw markdown content for this node
     * @param array<MarkdownNode> $children Child nodes (for headings), empty for leaf nodes
     */
    public function __construct(
        public readonly int $tokenCount,
        public readonly string $raw,
        public readonly array $children = [],
    ) {}

    /**
     * Calculate total token count including all descendants.
     */
    public function totalTokens(): int
    {
        $total = $this->tokenCount;

        foreach ($this->children as $child) {
            $total += $child->totalTokens();
        }

        return $total;
    }
}
