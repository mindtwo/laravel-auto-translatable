<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Services\Markdown\Chunking;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\Table\Table;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;

/**
 * Parses a CommonMark document into a hierarchical tree of MarkdownNodes.
 */
class Parser
{
    /** @var array<Node> */
    private array $processed = [];

    public function __construct(
        private readonly Tokenizer $tokenizer,
        private readonly string $markdown,
    ) {}

    /**
     * Parse the document into a hierarchical tree of nodes.
     *
     * @return array<MarkdownNode>
     */
    public function parse(Document $document): array
    {
        $this->processed = [];
        $nodes = [];

        foreach ($document->children() as $child) {
            if (in_array($child, $this->processed, true)) {
                continue;
            }

            if (($node = $this->parseNode($child)) instanceof MarkdownNode) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    private function parseNode(Node $node): ?MarkdownNode
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

    private function parseHeading(Heading $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);
        $level = $node->getLevel();

        // Collect all children under this heading
        $children = [];
        $nextNode = $node->next();

        while ($nextNode instanceof Node) {
            // Stop at same or higher level heading
            if ($nextNode instanceof Heading && $nextNode->getLevel() <= $level) {
                break;
            }

            if (! in_array($nextNode, $this->processed, true) && $parsed = $this->parseNode($nextNode)) {
                $children[] = $parsed;
            }

            $nextNode = $nextNode->next();
        }

        return new MarkdownNode($tokenCount, $raw, $children);
    }

    private function parseFencedCode(FencedCode $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    private function parseIndentedCode(IndentedCode $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    private function parseTable(Table $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    private function parseText(Node $node): MarkdownNode
    {
        $raw = $this->extractRaw($node);
        $tokenCount = $this->tokenizer->count($raw);

        return new MarkdownNode($tokenCount, $raw);
    }

    /**
     * Extract raw markdown for a node from the original source.
     */
    private function extractRaw(Node $node): string
    {
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
