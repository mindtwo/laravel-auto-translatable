<?php

use Mindtwo\AutoTranslatable\Services\MarkdownChunker;

it('returns single chunk for small content', function () {
    $chunker = new MarkdownChunker(3000);
    $content = "# Hello\n\nThis is a short test.";

    $chunks = $chunker->chunk($content);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0]['content'])->toBe($content);
    expect($chunks[0]['type'])->toBe('complete');
});

it('respects code block boundaries', function () {
    $chunker = new MarkdownChunker(100); // Very small to force chunking

    $content = <<<'MD'
# Test

Some text before code.

```php
function test() {
    return true;
}
```

Some text after code.
MD;

    $chunks = $chunker->chunk($content);

    // Code block should never be split
    foreach ($chunks as $chunk) {
        $codeBlockStarts = substr_count($chunk['content'], '```');
        // Code blocks must have even number of ``` (opening and closing)
        expect($codeBlockStarts % 2)->toBe(0);
    }
});

it('estimates tokens correctly', function () {
    $chunker = new MarkdownChunker();

    $content = 'Hello World'; // ~11 characters
    $tokens = $chunker->estimateTokens($content);

    // Should be around 3-4 tokens (11 / 3.5)
    expect($tokens)->toBeGreaterThan(2);
    expect($tokens)->toBeLessThan(5);
});

it('splits at safe boundaries', function () {
    $chunker = new MarkdownChunker(50); // Small to force splitting

    $content = <<<'MD'
# Header 1

Paragraph 1

## Header 2

Paragraph 2

### Header 3

Paragraph 3
MD;

    $chunks = $chunker->chunk($content);

    expect($chunks)->toBeGreaterThan(1);

    // Each chunk should be valid markdown
    foreach ($chunks as $chunk) {
        expect($chunk['content'])->not->toBeEmpty();
    }
});
