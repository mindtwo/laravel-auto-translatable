<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services;

use InvalidArgumentException;
use Mindtwo\AutoTranslatable\Contracts\ChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\MarkdownChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\NoChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\PlainTextChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\MarkdownChunker;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

class ChunkingStrategyResolver
{
    /** The markdown chunking strategy. */
    private readonly MarkdownChunkingStrategy $markdownStrategy;

    /** The plain text chunking strategy. */
    private readonly PlainTextChunkingStrategy $plainTextStrategy;

    /** The pass-through chunking strategy. */
    private readonly NoChunkingStrategy $noChunkingStrategy;

    /**
     * Create a new chunking strategy resolver.
     */
    public function __construct(MarkdownChunker $markdownChunker, Tokenizer $tokenizer)
    {
        $this->markdownStrategy = new MarkdownChunkingStrategy($markdownChunker);
        $this->plainTextStrategy = new PlainTextChunkingStrategy($tokenizer);
        $this->noChunkingStrategy = new NoChunkingStrategy;
    }

    /**
     * Resolve the chunking strategy for the given content.
     *
     * Pass an explicit strategy name ("markdown", "plain", "none") to bypass detection.
     * The default "auto" picks markdown when the content contains markdown syntax and
     * falls back to plain text otherwise.
     */
    public function resolve(string $content, ?string $strategyName = null): ChunkingStrategy
    {
        if ($strategyName !== null && $strategyName !== 'auto') {
            return match ($strategyName) {
                'markdown' => $this->markdownStrategy,
                'plain' => $this->plainTextStrategy,
                'none' => $this->noChunkingStrategy,
                default => throw new InvalidArgumentException("Unknown chunking strategy: {$strategyName}"),
            };
        }

        if ($this->markdownStrategy->canHandle($content)) {
            return $this->markdownStrategy;
        }

        return $this->plainTextStrategy;
    }
}
