<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

/**
 * Hierarchical greedy packing chunker for parsed markdown trees.
 *
 * The algorithm walks the tree in this order:
 *   1. Return the entire document as one chunk when it fits.
 *   2. Split at heading boundaries when it does not.
 *   3. Greedily pack sibling headings that fit together.
 *   4. Recurse into headings that exceed the budget.
 *   5. As a last resort, split large leaves at safe boundaries.
 */
class Chunker
{
    /**
     * Create a new chunker for the given tokenizer and token budget.
     */
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly int $maxTokens,
    ) {}

    /**
     * Chunk the given markdown nodes into rendered string chunks.
     *
     * @param array<int, MarkdownNode> $nodes
     *
     * @return array<int, string>
     */
    public function chunk(array $nodes): array
    {
        $chunks = $this->processNodes($nodes);

        return array_map(fn (array $pieces) => $this->renderPieces($pieces), $chunks);
    }

    /**
     * Process the top-level nodes and return chunks as arrays of markdown pieces.
     *
     * @param array<int, MarkdownNode> $nodes
     *
     * @return array<int, array<int, string>>
     */
    private function processNodes(array $nodes): array
    {
        [$preamble, $headings] = $this->separateHeadings($nodes);

        // First try to fit the entire document in a single chunk.
        if (! empty($preamble) && ! empty($headings)) {
            $totalTokens = $this->countPieces($preamble) + $this->countHeadings($headings);

            if ($totalTokens <= $this->maxTokens) {
                $allPieces = [...$preamble];

                foreach ($headings as $heading) {
                    $allPieces = [...$allPieces, ...$this->flattenHeading($heading)];
                }

                return [$allPieces];
            }
        }

        $chunks = [];

        // Emit the preamble first, splitting it further if it exceeds the budget.
        if (! empty($preamble)) {
            $preambleTokens = $this->countPieces($preamble);

            if ($preambleTokens > $this->maxTokens) {
                $chunks = [...$chunks, ...$this->splitPieces($preamble)];
            } else {
                $chunks[] = $preamble;
            }
        }

        return [...$chunks, ...$this->packHeadings($headings)];
    }

    /**
     * Greedily pack sibling headings into chunks that fit the token budget.
     *
     * @param array<int, MarkdownNode> $headings
     *
     * @return array<int, array<int, string>>
     */
    private function packHeadings(array $headings): array
    {
        $chunks = [];
        $accumulated = [];
        $currentTokens = 0;

        foreach ($headings as $heading) {
            $headingTokens = $heading->totalTokens();

            if ($currentTokens + $headingTokens <= $this->maxTokens && ! empty($accumulated)) {
                $accumulated = [...$accumulated, ...$this->flattenHeading($heading)];
                $currentTokens += $headingTokens;
            } else {
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                if ($headingTokens <= $this->maxTokens) {
                    $accumulated = $this->flattenHeading($heading);
                    $currentTokens = $headingTokens;
                } else {
                    // The heading itself is oversized; descend and split recursively.
                    $chunks = [...$chunks, ...$this->processHeading($heading)];
                    $accumulated = [];
                    $currentTokens = 0;
                }
            }
        }

        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Process a single heading whose subtree exceeds the token budget.
     *
     * @return array<int, array<int, string>>
     */
    private function processHeading(MarkdownNode $heading): array
    {
        [$directContent, $childHeadings] = $this->separateHeadings($heading->children);

        $headingPiece = [$heading->raw];
        $directTokens = $this->countPieces($directContent);
        $headingTokens = $heading->tokenCount;

        // Base case: a heading with no nested headings.
        if (empty($childHeadings)) {
            if ($headingTokens + $directTokens <= $this->maxTokens) {
                return [[...$headingPiece, ...$directContent]];
            }

            return $this->splitPieces([...$headingPiece, ...$directContent]);
        }

        // Attempt to fit the heading together with every descendant.
        $totalTokens = $heading->totalTokens();

        if ($totalTokens <= $this->maxTokens) {
            $allPieces = [...$headingPiece, ...$directContent];

            foreach ($childHeadings as $child) {
                $allPieces = [...$allPieces, ...$this->flattenHeading($child)];
            }

            return [$allPieces];
        }

        // Otherwise greedily pack the children alongside the heading body.
        $chunks = [];
        $accumulated = [...$headingPiece, ...$directContent];
        $currentTokens = $headingTokens + $directTokens;

        foreach ($childHeadings as $i => $child) {
            $childTokens = $child->totalTokens();

            if ($currentTokens + $childTokens <= $this->maxTokens) {
                $accumulated = [...$accumulated, ...$this->flattenHeading($child)];
                $currentTokens += $childTokens;
            } else {
                // $accumulated is non-empty here because it was seeded with the
                // heading and its direct content above.
                $chunks[] = $accumulated;

                // Defer the rest of the children to the sibling packer.
                $remainingChildren = array_slice($childHeadings, $i);

                return [...$chunks, ...$this->packHeadings($remainingChildren)];
            }
        }

        $chunks[] = $accumulated;

        return $chunks;
    }

    /**
     * Flatten a heading and every descendant into a flat list of markdown pieces.
     *
     * @return array<int, string>
     */
    private function flattenHeading(MarkdownNode $heading): array
    {
        $pieces = [$heading->raw];

        foreach ($heading->children as $child) {
            if (! empty($child->children)) {
                $pieces = [...$pieces, ...$this->flattenHeading($child)];
            } else {
                $pieces[] = $child->raw;
            }
        }

        return $pieces;
    }

    /**
     * Split a list of pieces into chunks that respect the token budget.
     *
     * @param array<int, string> $pieces
     *
     * @return array<int, array<int, string>>
     */
    private function splitPieces(array $pieces): array
    {
        $chunks = [];
        $accumulated = [];
        $currentTokens = 0;

        foreach ($pieces as $piece) {
            $pieceTokens = $this->tokenizer->count($piece);

            if ($currentTokens + $pieceTokens <= $this->maxTokens && ! empty($accumulated)) {
                $accumulated[] = $piece;
                $currentTokens += $pieceTokens;
            } else {
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                if ($pieceTokens > $this->maxTokens) {
                    $subChunks = $this->splitLargeText($piece);
                    $chunks = [...$chunks, ...$subChunks];
                    $accumulated = [];
                    $currentTokens = 0;
                } else {
                    $accumulated = [$piece];
                    $currentTokens = $pieceTokens;
                }
            }
        }

        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Split an oversized piece of text at line boundaries.
     *
     * @return array<int, array<int, string>>
     */
    private function splitLargeText(string $text): array
    {
        $lines = explode("\n", $text);

        // A single-line block cannot be split any further.
        if (count($lines) === 1) {
            return [[$text]];
        }

        $chunks = [];
        $accumulated = [];
        $currentTokens = 0;

        foreach ($lines as $line) {
            $line = mb_trim($line);

            if ($line === '') {
                continue;
            }

            $lineTokens = $this->tokenizer->count($line);

            if ($currentTokens + $lineTokens <= $this->maxTokens && ! empty($accumulated)) {
                $accumulated[] = $line;
                $currentTokens += $lineTokens;
            } else {
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                $accumulated = [$line];
                $currentTokens = $lineTokens;
            }
        }

        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Separate heading nodes from leaf content nodes.
     *
     * @param array<int, MarkdownNode> $nodes
     *
     * @return array{0: array<int, string>, 1: array<int, MarkdownNode>}
     */
    private function separateHeadings(array $nodes): array
    {
        $content = [];
        $headings = [];

        foreach ($nodes as $node) {
            if (! empty($node->children)) {
                $headings[] = $node;
            } else {
                $content[] = $node->raw;
            }
        }

        return [$content, $headings];
    }

    /**
     * Sum the total tokens across the given headings.
     *
     * @param array<int, MarkdownNode> $headings
     */
    private function countHeadings(array $headings): int
    {
        $total = 0;

        foreach ($headings as $heading) {
            $total += $heading->totalTokens();
        }

        return $total;
    }

    /**
     * Sum the tokens across the given markdown pieces.
     *
     * @param array<int, string> $pieces
     */
    private function countPieces(array $pieces): int
    {
        $total = 0;

        foreach ($pieces as $piece) {
            $total += $this->tokenizer->count($piece);
        }

        return $total;
    }

    /**
     * Render a chunk by joining its pieces with paragraph spacing.
     *
     * @param array<int, string> $pieces
     */
    private function renderPieces(array $pieces): string
    {
        return implode("\n\n", array_filter($pieces, fn ($p) => mb_trim($p) !== ''));
    }
}
