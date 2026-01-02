<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

/**
 * Hierarchical greedy packing chunker.
 *
 * Strategy:
 * 1. Try to fit entire content in one chunk
 * 2. If too large, split at heading boundaries
 * 3. Greedily pack sibling headings that fit together
 * 4. Recursively process headings that don't fit
 * 5. Split large content blocks (text, code, tables) at safe boundaries
 */
class Chunker
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly int $maxTokens,
    ) {}

    /**
     * Chunk a list of markdown nodes into string chunks.
     *
     * @param array<MarkdownNode> $nodes
     *
     * @return array<string>
     */
    public function chunk(array $nodes): array
    {
        $chunks = $this->processNodes($nodes);

        return array_map(fn (array $pieces) => $this->renderPieces($pieces), $chunks);
    }

    /**
     * Process nodes and return chunks as arrays of markdown strings.
     *
     * @param array<MarkdownNode> $nodes
     *
     * @return array<array<string>>
     */
    private function processNodes(array $nodes): array
    {
        // Separate headings from preamble content
        [$preamble, $headings] = $this->separateHeadings($nodes);

        // Try to fit everything in one chunk
        if (! empty($preamble) && ! empty($headings)) {
            $totalTokens = $this->countPieces($preamble) + $this->countHeadings($headings);

            if ($totalTokens <= $this->maxTokens) {
                // Everything fits!
                $allPieces = [...$preamble];

                foreach ($headings as $heading) {
                    $allPieces = [...$allPieces, ...$this->flattenHeading($heading)];
                }

                return [$allPieces];
            }
        }

        // Can't fit everything - process separately
        $chunks = [];

        // Add preamble chunks
        if (! empty($preamble)) {
            $preambleTokens = $this->countPieces($preamble);

            if ($preambleTokens > $this->maxTokens) {
                // Preamble too large - split it
                $chunks = [...$chunks, ...$this->splitPieces($preamble)];
            } else {
                $chunks[] = $preamble;
            }
        }

        // Greedily pack headings
        return [...$chunks, ...$this->packHeadings($headings)];
    }

    /**
     * Greedily pack sibling headings that fit together.
     *
     * @param array<MarkdownNode> $headings
     *
     * @return array<array<string>>
     */
    private function packHeadings(array $headings): array
    {
        $chunks = [];
        $accumulated = [];
        $currentTokens = 0;

        foreach ($headings as $heading) {
            $headingTokens = $heading->totalTokens();

            // Can we fit this heading with current accumulation?
            if ($currentTokens + $headingTokens <= $this->maxTokens && ! empty($accumulated)) {
                // Yes - add it
                $accumulated = [...$accumulated, ...$this->flattenHeading($heading)];
                $currentTokens += $headingTokens;
            } else {
                // Emit accumulated if any
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                // Check if this heading fits on its own
                if ($headingTokens <= $this->maxTokens) {
                    // Start new accumulation
                    $accumulated = $this->flattenHeading($heading);
                    $currentTokens = $headingTokens;
                } else {
                    // Too large - process recursively
                    $chunks = [...$chunks, ...$this->processHeading($heading)];
                    $accumulated = [];
                    $currentTokens = 0;
                }
            }
        }

        // Emit remaining
        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Process a single heading that exceeds maxTokens.
     *
     * @return array<array<string>>
     */
    private function processHeading(MarkdownNode $heading): array
    {
        [$directContent, $childHeadings] = $this->separateHeadings($heading->children);

        $headingPiece = [$heading->raw];
        $directTokens = $this->countPieces($directContent);
        $headingTokens = $heading->tokenCount;

        // Base case: no child headings
        if (empty($childHeadings)) {
            if ($headingTokens + $directTokens <= $this->maxTokens) {
                return [[...$headingPiece, ...$directContent]];
            }

            // Direct content too large - split it
            return $this->splitPieces([...$headingPiece, ...$directContent]);
        }

        // Try to fit everything (heading + direct + all children)
        $totalTokens = $heading->totalTokens();

        if ($totalTokens <= $this->maxTokens) {
            $allPieces = [...$headingPiece, ...$directContent];

            foreach ($childHeadings as $child) {
                $allPieces = [...$allPieces, ...$this->flattenHeading($child)];
            }

            return [$allPieces];
        }

        // Can't fit - greedy pack children
        $chunks = [];
        $accumulated = [...$headingPiece, ...$directContent];
        $currentTokens = $headingTokens + $directTokens;

        foreach ($childHeadings as $i => $child) {
            $childTokens = $child->totalTokens();

            if ($currentTokens + $childTokens <= $this->maxTokens) {
                // Fits - add it
                $accumulated = [...$accumulated, ...$this->flattenHeading($child)];
                $currentTokens += $childTokens;
            } else {
                // Doesn't fit - emit accumulated and pack remaining children
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                // Pack all remaining children (including current one) together
                $remainingChildren = array_slice($childHeadings, $i);

                return [...$chunks, ...$this->packHeadings($remainingChildren)];
            }
        }

        // Emit remaining
        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Flatten a heading and all descendants into markdown pieces.
     *
     * @return array<string>
     */
    private function flattenHeading(MarkdownNode $heading): array
    {
        $pieces = [$heading->raw];

        foreach ($heading->children as $child) {
            if (! empty($child->children)) {
                // Child is a heading - recurse
                $pieces = [...$pieces, ...$this->flattenHeading($child)];
            } else {
                // Child is a leaf node - add raw content
                $pieces[] = $child->raw;
            }
        }

        return $pieces;
    }

    /**
     * Split pieces that exceed maxTokens into smaller chunks.
     *
     * @param array<string> $pieces
     *
     * @return array<array<string>>
     */
    private function splitPieces(array $pieces): array
    {
        $chunks = [];
        $accumulated = [];
        $currentTokens = 0;

        foreach ($pieces as $piece) {
            $pieceTokens = $this->tokenizer->count($piece);

            // Try to fit in current chunk
            if ($currentTokens + $pieceTokens <= $this->maxTokens && ! empty($accumulated)) {
                $accumulated[] = $piece;
                $currentTokens += $pieceTokens;
            } else {
                // Emit current chunk
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                // Check if piece itself is too large
                if ($pieceTokens > $this->maxTokens) {
                    // Split large text at line boundaries and accumulate
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
     * Split large text content at line boundaries, accumulating into max-sized chunks.
     *
     * @return array<array<string>>
     */
    private function splitLargeText(string $text): array
    {
        // Try splitting at line breaks first
        $lines = explode("\n", $text);

        // If single line, return as-is (can't split further)
        if (count($lines) === 1) {
            return [[$text]];
        }

        // Accumulate lines into chunks that fit under maxTokens
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
                // Emit current chunk
                if (! empty($accumulated)) {
                    $chunks[] = $accumulated;
                }

                // Start new chunk with this line
                $accumulated = [$line];
                $currentTokens = $lineTokens;
            }
        }

        // Emit remaining
        if (! empty($accumulated)) {
            $chunks[] = $accumulated;
        }

        return $chunks;
    }

    /**
     * Separate headings from other content.
     *
     * @param array<MarkdownNode> $nodes
     *
     * @return array{array<string>, array<MarkdownNode>}
     */
    private function separateHeadings(array $nodes): array
    {
        $content = [];
        $headings = [];

        foreach ($nodes as $node) {
            if (! empty($node->children)) {
                // Node has children - it's a heading
                $headings[] = $node;
            } else {
                // Leaf node - add to content
                $content[] = $node->raw;
            }
        }

        return [$content, $headings];
    }

    /**
     * @param array<MarkdownNode> $headings
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
     * @param array<string> $pieces
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
     * @param array<string> $pieces
     */
    private function renderPieces(array $pieces): string
    {
        return implode("\n\n", array_filter($pieces, fn ($p) => mb_trim($p) !== ''));
    }
}
