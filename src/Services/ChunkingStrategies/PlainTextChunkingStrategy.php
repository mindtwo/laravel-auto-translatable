<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

class PlainTextChunkingStrategy implements ChunkingStrategy
{
    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function chunk(string $content, int $maxTokens): array
    {
        if ($this->tokenizer->count($content) <= $maxTokens) {
            return [$content];
        }

        // Try splitting at paragraph boundaries (line breaks)
        $chunks = $this->chunkByParagraphs($content, $maxTokens);

        if ($chunks !== null) {
            return $chunks;
        }

        // Paragraphs too large - try sentences
        $chunks = $this->chunkBySentences($content, $maxTokens);

        if ($chunks !== null) {
            return $chunks;
        }

        // Sentences too large - fall back to word boundaries
        return $this->chunkByWords($content, $maxTokens);
    }

    /**
     * {@inheritDoc}
     */
    public function canHandle(string $content): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return 'plain';
    }

    /**
     * Chunk by paragraph boundaries (line breaks).
     */
    private function chunkByParagraphs(string $content, int $maxTokens): ?array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(\n+)/', allowOversized: false);
    }

    /**
     * Chunk by sentence boundaries.
     */
    private function chunkBySentences(string $content, int $maxTokens): ?array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(?<=[.!?])(\s+)(?=\S)/', allowOversized: false);
    }

    /**
     * Chunk by word boundaries (last resort).
     */
    private function chunkByWords(string $content, int $maxTokens): array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(\s+)/', allowOversized: true) ?? [$content];
    }

    /**
     * Generic chunking method that splits on a pattern and preserves separators.
     */
    private function chunkByPattern(string $content, int $maxTokens, string $pattern, bool $allowOversized): ?array
    {
        $parts = preg_split($pattern, $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false || count($parts) === 1) {
            return null;
        }

        $chunks = [];
        $currentChunk = [];
        $currentTokens = 0;

        for ($i = 0; $i < count($parts); ++$i) {
            $part = $parts[$i];
            $isText = $i % 2 === 0; // Even indices are text, odd are separators

            if ($isText) {
                $partTokens = $this->tokenizer->count($part);

                // Check if this text block is too large on its own
                if ($partTokens > $maxTokens && ! $allowOversized) {
                    return null;
                }

                // Try to fit in current chunk
                if ($currentTokens + $partTokens <= $maxTokens && ! empty($currentChunk)) {
                    $currentChunk[] = $part;
                    $currentTokens += $partTokens;
                } else {
                    if (! empty($currentChunk)) {
                        $chunks[] = implode('', $currentChunk);
                    }

                    // Start new chunk
                    $currentChunk = [$part];
                    $currentTokens = $partTokens;
                }
            } else {
                // It's a separator - add it to current chunk (preserves original formatting)
                $currentChunk[] = $part;
            }
        }

        if (! empty($currentChunk)) {
            $chunks[] = implode('', $currentChunk);
        }

        return $chunks;
    }
}
