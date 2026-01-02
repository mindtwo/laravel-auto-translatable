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
    private readonly MarkdownChunkingStrategy $markdownStrategy;
    private readonly PlainTextChunkingStrategy $plainTextStrategy;
    private readonly NoChunkingStrategy $noChunkingStrategy;

    public function __construct(MarkdownChunker $markdownChunker, Tokenizer $tokenizer)
    {
        $this->markdownStrategy = new MarkdownChunkingStrategy($markdownChunker);
        $this->plainTextStrategy = new PlainTextChunkingStrategy($tokenizer);
        $this->noChunkingStrategy = new NoChunkingStrategy;
    }

    /**
     * Resolve the appropriate chunking strategy.
     *
     * @param string $content The content to chunk
     * @param string|null $strategyName Explicit strategy name ('markdown', 'plain', 'none', 'auto')
     */
    public function resolve(string $content, ?string $strategyName = null): ChunkingStrategy
    {
        // Handle explicit strategy names
        if ($strategyName !== null && $strategyName !== 'auto') {
            return match ($strategyName) {
                'markdown' => $this->markdownStrategy,
                'plain' => $this->plainTextStrategy,
                'none' => $this->noChunkingStrategy,
                default => throw new InvalidArgumentException("Unknown chunking strategy: {$strategyName}"),
            };
        }

        // Auto-detect: try each strategy in order
        if ($this->markdownStrategy->canHandle($content)) {
            return $this->markdownStrategy;
        }

        // Plain text is the fallback (always returns true)
        return $this->plainTextStrategy;
    }
}
