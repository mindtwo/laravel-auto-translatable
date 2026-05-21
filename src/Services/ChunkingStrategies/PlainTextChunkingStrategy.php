<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\ChunkingStrategies;

use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

class PlainTextChunkingStrategy implements ChunkingStrategy
{
    /**
     * Create a new plain text chunking strategy.
     */
    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {}

    /**
     * Chunk plain text into pieces that respect natural language boundaries.
     *
     * The strategy walks a hierarchy of progressively finer boundaries until
     * every chunk fits within the token budget: paragraphs, sentences, words.
     *
     * @return array<int, string>
     */
    public function chunk(string $content, int $maxTokens): array
    {
        if ($this->tokenizer->count($content) <= $maxTokens) {
            return [$content];
        }

        $chunks = $this->chunkByParagraphs($content, $maxTokens);

        if ($chunks !== null) {
            return $chunks;
        }

        $chunks = $this->chunkBySentences($content, $maxTokens);

        if ($chunks !== null) {
            return $chunks;
        }

        return $this->chunkByWords($content, $maxTokens);
    }

    /**
     * Determine if this strategy can handle the given content.
     *
     * The plain text strategy is the universal fallback and accepts any input.
     */
    public function canHandle(string $content): bool
    {
        return true;
    }

    /**
     * Get the strategy identifier.
     */
    public function getName(): string
    {
        return 'plain';
    }

    /**
     * Chunk the content at paragraph boundaries.
     *
     * @return array<int, string>|null
     */
    private function chunkByParagraphs(string $content, int $maxTokens): ?array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(\n+)/', allowOversized: false);
    }

    /**
     * Chunk the content at sentence boundaries.
     *
     * @return array<int, string>|null
     */
    private function chunkBySentences(string $content, int $maxTokens): ?array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(?<=[.!?])(\s+)(?=\S)/', allowOversized: false);
    }

    /**
     * Chunk the content at word boundaries as a last resort.
     *
     * @return array<int, string>
     */
    private function chunkByWords(string $content, int $maxTokens): array
    {
        return $this->chunkByPattern($content, $maxTokens, '/(\s+)/', allowOversized: true) ?? [$content];
    }

    /**
     * Split the content on the given pattern and pack the parts into chunks.
     *
     * Returns null when the content cannot be split without exceeding the
     * budget unless $allowOversized is true.
     *
     * @return array<int, string>|null
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
            $isText = $i % 2 === 0; // even indices are text, odd indices are separators

            if ($isText) {
                $partTokens = $this->tokenizer->count($part);

                if ($partTokens > $maxTokens && ! $allowOversized) {
                    return null;
                }

                if ($currentTokens + $partTokens <= $maxTokens && ! empty($currentChunk)) {
                    $currentChunk[] = $part;
                    $currentTokens += $partTokens;
                } else {
                    if (! empty($currentChunk)) {
                        $chunks[] = implode('', $currentChunk);
                    }

                    $currentChunk = [$part];
                    $currentTokens = $partTokens;
                }
            } else {
                // Preserve the original separator so the joined output is byte-identical.
                $currentChunk[] = $part;
            }
        }

        if (! empty($currentChunk)) {
            $chunks[] = implode('', $currentChunk);
        }

        return $chunks;
    }
}
