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
use Mindtwo\AutoTranslatable\Support\Config;

/**
 * Splits markdown content into LLM-sized chunks while preserving structure.
 *
 * Headings, code blocks, and tables are never broken mid-section. Sibling
 * sections that fit together are packed greedily to minimise round trips.
 */
class MarkdownChunker
{
    /** The configured CommonMark parser. */
    private readonly MarkdownParser $parser;

    /**
     * Create a new markdown chunker.
     */
    public function __construct(
        private readonly Tokenizer $tokenizer,
    ) {
        $env = new Environment;
        $env->addExtension(new CommonMarkCoreExtension);
        $env->addExtension(new TableExtension);
        $this->parser = new MarkdownParser($env);
    }

    /**
     * Chunk the given markdown into pieces that respect the token budget.
     *
     * Pass null for $maxTokens to use the value from configuration.
     *
     * @return array<int, string>
     *
     * @throws CommonMarkException
     */
    public function chunk(string $markdown, ?int $maxTokens = null): array
    {
        if ($markdown === '') {
            return [''];
        }

        $markdown = mb_rtrim($markdown);

        $maxTokens ??= Config::int('auto-translatable.chunk_size', 80000);

        if ($maxTokens <= 0) {
            throw new InvalidArgumentException('maxTokens must be positive');
        }

        // Short-circuit when the content already fits within a single chunk.
        if ($this->tokenizer->count($markdown) <= $maxTokens) {
            return [$markdown];
        }

        $doc = $this->parser->parse($markdown);

        $parser = new Parser($this->tokenizer, $markdown);
        $nodes = $parser->parse($doc);

        $chunker = new Chunker($this->tokenizer, $maxTokens);

        return $chunker->chunk($nodes);
    }

    /**
     * Estimate the number of tokens in the given text.
     */
    public function estimateTokens(string $text): int
    {
        return $this->tokenizer->count($text);
    }
}
