<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\Markdown\MarkdownChunker;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;

beforeEach(function (): void {
    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
    $this->chunker = resolve(MarkdownChunker::class);
});

it('returns single chunk for small content', function (): void {
    config(['auto-translatable.chunk_size' => 3000]);

    $content = "# Hello\n\nThis is a short test.";

    $chunks = $this->chunker->chunk($content);

    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toBe($content);
});

it('respects code block boundaries', function (): void {
    config(['auto-translatable.chunk_size' => 100]);

    $content = <<<'MD'
        # Test

        {80 tokens}

        ```php
        function test() {
            return true;
        }
        ```

        {25 tokens}
        MD;

    $chunks = $this->chunker->chunk($content);

    // Code block should never be split
    foreach ($chunks as $chunk) {
        $codeBlockStarts = mb_substr_count($chunk, '```');
        // Code blocks must have even number of ``` (opening and closing)
        expect($codeBlockStarts % 2)->toBe(0);
    }
});

it('splits at safe boundaries', function (): void {
    config(['auto-translatable.chunk_size' => 50]);

    $content = <<<'MD'
        # Header 1

        Paragraph 1

        ## Header 2

        Paragraph 2

        ### Header 3

        Paragraph 3
        MD;

    $chunks = $this->chunker->chunk($content);

    expect($chunks)->toBeGreaterThan(1);
});

it('handles content exactly at chunk boundary', function (): void {
    config(['auto-translatable.chunk_size' => 3000]);

    $content = '{3000 tokens}';
    $chunks = $this->chunker->chunk($content);
    // Should return single chunk when exactly at boundary
    expect($chunks)->toHaveCount(1);

    // Now test just over the boundary
    $content = "{3000 tokens}\n{2 tokens}";
    $chunks = $this->chunker->chunk($content);
    expect($chunks)->toHaveCount(2);
});

it('preserves markdown tables', function (): void {
    config(['auto-translatable.chunk_size' => 100]);

    $content = <<<'MD'
        # Data

        | Name | Age | City |
        |------|-----|------|
        | John | 25  | NYC  |
        | Jane | 30  | LA   |

        More content here.
        MD;

    $chunks = $this->chunker->chunk($content);

    expect($chunks)->toBeArray();
});

it('handles very long single lines', function (): void {
    config(['auto-translatable.chunk_size' => 100]);

    // A single paragraph with no line breaks
    $longLine = str_repeat('This is a very long line without any breaks. ', 50);
    $content = "# Title\n\n".$longLine;

    $chunks = $this->chunker->chunk($content);

    // Should still chunk it
    expect($chunks)->toBeGreaterThan(1);
});

it('greedy packs sibling sections that fit together', function (): void {
    $content = <<<'MD'
        # Large Section
        {480 tokens}

        # Small Section
        {40 tokens}

        # Medium Section
        {120 tokens}
        MD;

    $chunks = $this->chunker->chunk($content, maxTokens: 500);

    // Large Section (480 + ~4 heading) exceeds 500 when combined with others
    // But Small Section (40 + ~5) + Validation (120 + ~5) = ~170 fits together!
    expect(count($chunks))->toBe(2);

    $smallSectionChunk = null;
    $mediumSectionChunk = null;

    foreach ($chunks as $i => $chunk) {
        if (str_contains($chunk, '# Small Section')) {
            $smallSectionChunk = $i;
        }

        if (str_contains($chunk, '# Medium Section')) {
            $mediumSectionChunk = $i;
        }
    }

    // Both should be in the same chunk (greedy packing)
    expect($smallSectionChunk)->not->toBeNull();
    expect($mediumSectionChunk)->not->toBeNull();
    expect($smallSectionChunk)->toBe($mediumSectionChunk, 'Small Section and Medium Section should be packed together');
});

it('handles numbered lists correctly', function (): void {
    config(['auto-translatable.chunk_size' => 150]);

    $content = <<<'MD'
        # Steps

        1. First step with some content
        2. Second step with more details
        3. Third step
        4. Fourth step with even more information
        5. Final step

        Conclusion text.
        MD;

    $chunks = $this->chunker->chunk($content);

    expect($chunks)->toBeArray();
});

it('handles content with HTML tags', function (): void {
    config(['auto-translatable.chunk_size' => 150]);

    $content = <<<'MD'
        # HTML in Markdown

        <div class="alert">
        This is an alert
        </div>

        Regular **markdown** text.

        <img src="test.jpg" alt="Test">

        More content.
        MD;

    $chunks = $this->chunker->chunk($content);

    expect($chunks)->toBeArray();
});

it('handles preamble content when everything fits', function (): void {
    $content = <<<'MD'
        This is preamble content before any headings.
        {200 tokens}

        # First Heading
        {300 tokens}

        ## Subheading
        {400 tokens}
        MD;

    $chunks = $this->chunker->chunk($content, maxTokens: 1000);

    // Total is ~900 tokens which fits under maxTokens (1000), so everything in one chunk
    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toContain('preamble content');
    expect($chunks[0])->toContain('# First Heading');
    expect($chunks[0])->toContain('## Subheading');
});

it('handles preamble content when it exceeds maxTokens', function (): void {
    $content = <<<'MD'
        This is preamble content before any headings.
        {200 tokens}

        # First Heading
        {500 tokens}

        ## Subheading
        {400 tokens}
        MD;

    $chunks = $this->chunker->chunk($content, maxTokens: 1000);

    // Total is ~1100 tokens which exceeds maxTokens (1000), so must split
    expect($chunks)->toHaveCount(2);

    // Chunk 1: Preamble only
    expect($chunks[0])->toContain('preamble content');
    expect($chunks[0])->not->toContain('# First Heading');

    // Chunk 2: First Heading + Subheading (~900 tokens, fits under maxTokens)
    expect($chunks[1])->toContain('# First Heading');
    expect($chunks[1])->toContain('## Subheading');
});

it('handles empty parent headings when content fits', function (): void {
    $content = <<<'MD'
        ## Parent Heading

        ### Child 1
        {300 tokens}

        ### Child 2
        {300 tokens}
        MD;

    $chunks = $this->chunker->chunk($content, maxTokens: 1000);

    // Total ~600 tokens fits under maxTokens
    expect($chunks)->toHaveCount(1);
    expect($chunks[0])->toContain('## Parent Heading');
    expect($chunks[0])->toContain('### Child 1');
    expect($chunks[0])->toContain('### Child 2');
});

it('handles empty parent headings when content must split', function (): void {
    $content = <<<'MD'
        ## Parent Heading

        ### Child 1
        {300 tokens}

        ### Child 2
        {800 tokens}
        MD;

    $chunks = $this->chunker->chunk($content, maxTokens: 1000);

    // Total ~1100 exceeds maxTokens, must split
    expect($chunks)->toHaveCount(2);

    // Chunk 1: Parent + Child 1 (fits under maxTokens)
    expect($chunks[0])->toContain('## Parent Heading');
    expect($chunks[0])->toContain('### Child 1');
    expect($chunks[0])->not->toContain('### Child 2');

    // Chunk 2: Child 2 alone
    expect($chunks[1])->toContain('### Child 2');
});
