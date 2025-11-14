<?php

namespace Mindtwo\AutoTranslatable\Services;

class MarkdownChunker
{
    public function __construct(
        protected int $maxTokens = 3000
    ) {}

    /**
     * Chunk markdown content into smaller pieces
     *
     * @return array<array{content: string, index: int, tokens: int, boundaries: array{start_line: int, end_line: int}, type: string}>
     */
    public function chunk(string $markdown, ?int $maxTokens = null): array
    {
        $maxTokens = $maxTokens ?? $this->maxTokens;

        // If content is small enough, return as single chunk
        $estimatedTokens = $this->estimateTokens($markdown);
        if ($estimatedTokens <= $maxTokens) {
            return [[
                'content' => $markdown,
                'index' => 0,
                'tokens' => $estimatedTokens,
                'boundaries' => ['start_line' => 1, 'end_line' => count(explode("\n", $markdown))],
                'type' => 'complete',
            ]];
        }

        // Split into lines for processing
        $lines = explode("\n", $markdown);
        $chunks = [];
        $currentChunk = [];
        $currentTokens = 0;
        $chunkIndex = 0;
        $startLine = 1;

        $i = 0;
        while ($i < count($lines)) {
            $line = $lines[$i];

            // Detect code blocks (must stay together)
            if ($this->isCodeBlockStart($line)) {
                $codeBlock = $this->extractCodeBlock($lines, $i);
                $blockTokens = $this->estimateTokens(implode("\n", $codeBlock['lines']));

                // If adding code block exceeds limit and we have content, save current chunk
                if ($currentTokens + $blockTokens > $maxTokens && ! empty($currentChunk)) {
                    $chunks[] = $this->createChunk(
                        $currentChunk,
                        $chunkIndex++,
                        $startLine,
                        $i
                    );
                    $currentChunk = [];
                    $currentTokens = 0;
                    $startLine = $i + 1;
                }

                // Add code block
                $currentChunk = array_merge($currentChunk, $codeBlock['lines']);
                $currentTokens += $blockTokens;
                $i = $codeBlock['endIndex'] + 1;

                continue;
            }

            // Check if line would exceed token limit
            $lineTokens = $this->estimateTokens($line);

            if ($currentTokens + $lineTokens > $maxTokens && ! empty($currentChunk)) {
                // Check if we should split at this point
                if ($this->isSafeSplitPoint($line)) {
                    // Save current chunk
                    $chunks[] = $this->createChunk(
                        $currentChunk,
                        $chunkIndex++,
                        $startLine,
                        $i
                    );
                    $currentChunk = [];
                    $currentTokens = 0;
                    $startLine = $i + 1;
                }
            }

            // Add line to current chunk
            $currentChunk[] = $line;
            $currentTokens += $lineTokens;
            $i++;
        }

        // Add remaining content as final chunk
        if (! empty($currentChunk)) {
            $chunks[] = $this->createChunk(
                $currentChunk,
                $chunkIndex,
                $startLine,
                count($lines)
            );
        }

        return $chunks;
    }

    /**
     * Estimate token count (rough approximation)
     */
    public function estimateTokens(string $content): int
    {
        // Rough estimate: 1 token ≈ 4 characters for English text
        // More conservative: 1 token ≈ 3.5 characters
        return (int) ceil(strlen($content) / 3.5);
    }

    /**
     * Check if line starts a code block
     */
    protected function isCodeBlockStart(string $line): bool
    {
        return preg_match('/^```/', $line) === 1;
    }

    /**
     * Extract complete code block
     *
     * @param  array<string>  $lines
     * @return array{lines: array<string>, endIndex: int}
     */
    protected function extractCodeBlock(array $lines, int $startIndex): array
    {
        $blockLines = [$lines[$startIndex]]; // Include opening ```
        $i = $startIndex + 1;

        // Find closing ```
        while ($i < count($lines)) {
            $blockLines[] = $lines[$i];

            if (preg_match('/^```\s*$/', $lines[$i])) {
                break; // Found closing marker
            }

            $i++;
        }

        return [
            'lines' => $blockLines,
            'endIndex' => $i,
        ];
    }

    /**
     * Check if this is a safe point to split content
     */
    protected function isSafeSplitPoint(string $line): bool
    {
        // Safe to split at:
        // - Empty lines (paragraph breaks)
        // - Markdown headers
        // - List items (new list item)
        // - Horizontal rules

        if (trim($line) === '') {
            return true;
        }

        // Header lines
        if (preg_match('/^#{1,6}\s/', $line)) {
            return true;
        }

        // Horizontal rules
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
            return true;
        }

        // List items (but be careful with mid-sentence)
        if (preg_match('/^(\s*[-*+]\s|\s*\d+\.\s)/', $line)) {
            return true;
        }

        return false;
    }

    /**
     * Create a chunk array from lines
     *
     * @param  array<string>  $lines
     * @return array{content: string, index: int, tokens: int, boundaries: array{start_line: int, end_line: int}, type: string}
     */
    protected function createChunk(
        array $lines,
        int $index,
        int $startLine,
        int $endLine
    ): array {
        $content = implode("\n", $lines);

        return [
            'content' => $content,
            'index' => $index,
            'tokens' => $this->estimateTokens($content),
            'boundaries' => [
                'start_line' => $startLine,
                'end_line' => $endLine,
            ],
            'type' => 'section',
        ];
    }
}
