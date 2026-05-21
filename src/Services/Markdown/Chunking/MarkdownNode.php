<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

/**
 * A node in the parsed markdown document tree.
 *
 * A node is either a leaf (text, code, table) or a heading with children.
 */
class MarkdownNode
{
    /**
     * Create a new markdown node.
     *
     * @param int $tokenCount number of tokens in this node alone, excluding children
     * @param string $raw raw markdown source for this node
     * @param array<int, MarkdownNode> $children child nodes (headings only); empty for leaves
     */
    public function __construct(
        public readonly int $tokenCount,
        public readonly string $raw,
        public readonly array $children = [],
    ) {}

    /**
     * Get the total token count for this node and all of its descendants.
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
