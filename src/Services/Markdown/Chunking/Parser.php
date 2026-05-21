<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

/**
 * Parses a CommonMark document into a hierarchical tree of MarkdownNodes.
 */
class Parser
{
    /**
     * The CommonMark nodes that have already been consumed.
     *
     * @var array<int, Node>
     */
    private array $processed = [];

    /**
     * Create a new parser instance.
     */
    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly string $markdown,
    ) {}

    /**
     * Parse the given document into a hierarchical tree of nodes.
     *
     * @return array<int, MarkdownNode>
     */
    public function parse(Document $document): array
    {
        $this->processed = [];
        $nodes = [];

        foreach ($document->children() as $child) {
            if (! $child instanceof Node) {
                continue;
            }

            /**
             * PHPStan narrows $this->processed to the empty literal after the
             * reset above and cannot see that parseNode() appends to it.
             *
             * @phpstan-ignore function.impossibleType
             */
            if (in_array($child, $this->processed, true)) {
                continue;
            }

            $nodes[] = $this->parseNode($child);
        }

        return $nodes;
    }

    /**
     * Dispatch a CommonMark node to the appropriate parser method.
     */
    private function parseNode(Node $node): MarkdownNode
    {
        $this->processed[] = $node;

        return match (true) {
            $node instanceof Heading => $this->parseHeading($node),
            $node instanceof FencedCode => $this->parseFencedCode($node),
            $node instanceof IndentedCode => $this->parseIndentedCode($node),
            $node instanceof Table => $this->parseTable($node),
            default => $this->parseText($node),
        };
    }

    /**
     * Parse a heading and gather every sibling that belongs under it.
     */
    private function parseHeading(Heading $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);
        $level = $node->getLevel();

        $children = [];
        $nextNode = $node->next();

        while ($nextNode instanceof Node) {
            // Stop at the next heading of the same or higher level.
            if ($nextNode instanceof Heading && $nextNode->getLevel() <= $level) {
                break;
            }

            if (! in_array($nextNode, $this->processed, true)) {
                $children[] = $this->parseNode($nextNode);
            }

            $nextNode = $nextNode->next();
        }

        return new MarkdownNode($tokenCount, $raw, $children);
    }

    /**
     * Parse a fenced code block.
     */
    private function parseFencedCode(FencedCode $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    /**
     * Parse an indented code block.
     */
    private function parseIndentedCode(IndentedCode $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    /**
     * Parse a table block.
     */
    private function parseTable(Table $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    /**
     * Parse any other node as a generic text block.
     */
    private function parseText(Node $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    /**
     * Extract the original markdown source for the given node.
     */
    private function extractRaw(Node $node): string
    {
        if (! $node instanceof AbstractBlock) {
            return '';
        }

        $start = $node->getStartLine();
        $end = $node->getEndLine();

        if ($start === null || $end === null) {
            return '';
        }

        $lines = explode("\n", $this->markdown);
        $extracted = array_slice($lines, $start - 1, $end - $start + 1);

        return implode("\n", $extracted);
    }
}
