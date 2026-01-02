<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown;

use InvalidArgumentException;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Exception\CommonMarkException;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Parser\MarkdownParser;
use Mindtwo\AutoTranslatable\Services\Markdown\Chunking\Chunker;
use Mindtwo\AutoTranslatable\Services\Markdown\Chunking\Parser;

/**
 * Intelligently chunks markdown content for LLM processing.
 *
 * Features:
 * - Preserves document structure (headings, code blocks, tables)
 * - Greedy packing: combines sibling sections that fit together
 * - Respects semantic boundaries
 * - Token-aware chunking
 */
class MarkdownChunker
{
    private readonly MarkdownParser $parser;

    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {
        // Setup CommonMark parser
        $env = new Environment;
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new TableExtension);
        $this->parser = new MarkdownParser($env);
    }

    /**
     * Chunk markdown content into smaller pieces.
     *
     * @param string $markdown The markdown content to chunk
     * @param int|null $maxTokens Maximum tokens per chunk (null = use config)
     *
     * @return array<string> Array of markdown chunks
     *
     * @throws CommonMarkException
     */
    public function chunk(string $markdown, ?int $maxTokens = null): array
    {
        // Handle edge cases
        if ($markdown === '') {
            return [''];
        }

        $markdown = mb_rtrim($markdown);

        // Get max tokens from config if not provided
        $maxTokens ??= config('auto-translatable.chunk_size', 80000);

        if ($maxTokens <= 0) {
            throw new InvalidArgumentException('maxTokens must be positive');
        }

        // Quick check: if content fits, return as-is
        if ($this->tokenizer->count($markdown) <= $maxTokens) {
            return [$markdown];
        }

        // Parse markdown into document tree
        $doc = $this->parser->parse($markdown);

        // Parse into our simplified node structure
        $parser = new Parser($this->tokenizer, $markdown);
        $nodes = $parser->parse($doc);

        // Chunk using greedy hierarchical packing
        $chunker = new Chunker($this->tokenizer, $maxTokens);

        return $chunker->chunk($nodes);
    }

    /**
     * Estimate token count for given text.
     */
    public function estimateTokens(string $text): int
    {
        return $this->tokenizer->count($text);
    }
}
