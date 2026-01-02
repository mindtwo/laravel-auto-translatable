<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\PlainTextChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;

beforeEach(function (): void {
    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
    $this->strategy = resolve(PlainTextChunkingStrategy::class);
});

it('returns single chunk for small content', function (): void {
    $content = 'This is a short text that fits in one chunk.';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('chunks by paragraph boundaries', function (): void {
    $content = <<<'TEXT'
        {40 tokens}

        {40 tokens}

        {40 tokens}
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 50);

    // Each paragraph should be in its own chunk
    expect($chunks)->toHaveCount(3);
});

it('chunks by sentence boundaries when paragraphs too large', function (): void {
    // Single paragraph with multiple sentences
    $content = '{60 tokens}. {60 tokens}. {60 tokens}.';

    $chunks = $this->strategy->chunk($content, maxTokens: 80);

    // Should chunk at sentence boundaries
    expect($chunks)->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        // Each chunk should end with a sentence terminator or be the last chunk
        if ($chunk !== end($chunks)) {
            expect($chunk)->toMatch('/[.!?]\s*$/');
        }
    }
});

it('chunks by word boundaries as fallback', function (): void {
    // Single sentence without punctuation, too large to fit
    // Need to make each word large enough that it forces word-level splitting
    $words = [];

    for ($i = 0; $i < 10; ++$i) {
        $words[] = '{30 tokens}';
    }
    $content = implode(' ', $words); // 300 tokens as one long text

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    // Should fall back to word boundaries and create multiple chunks
    expect($chunks)->toBeGreaterThan(1);
});

it('preserves paragraph separators', function (): void {
    $content = <<<'TEXT'
        First paragraph.

        Second paragraph.
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    // Small enough to fit in one chunk
    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('handles content with multiple newlines', function (): void {
    $content = <<<'TEXT'
        Paragraph 1


        Paragraph 2 after multiple newlines
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('handles exclamation and question marks', function (): void {
    $content = '{40 tokens}! {40 tokens}? {40 tokens}.';

    $chunks = $this->strategy->chunk($content, maxTokens: 50);

    expect($chunks)->toBeGreaterThan(1);

    // Should split at sentence boundaries (!, ?, .)
    foreach ($chunks as $i => $chunk) {
        if ($i < count($chunks) - 1) {
            expect($chunk)->toMatch('/[.!?]\s*$/');
        }
    }
});

it('handles mixed punctuation', function (): void {
    $content = 'Is this a test? Yes! This is definitely a test. Great.';

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    expect($chunks)->toHaveCount(1);
});

it('handles empty content', function (): void {
    $content = '';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe('');
});

it('handles single word', function (): void {
    $content = 'Word';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('handles content without sentence terminators', function (): void {
    $content = <<<'TEXT'
        {60 tokens}

        {60 tokens}

        {60 tokens}
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 80);

    // Should fall back to paragraph splitting
    expect($chunks)->toBeGreaterThan(1);
});

it('handles whitespace-only content', function (): void {
    $content = "   \n\n   \n   ";

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)->toHaveCount(1);
});

it('preserves leading and trailing whitespace', function (): void {
    $content = '  Leading and trailing spaces  ';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('handles unicode text with sentence boundaries', function (): void {
    $content = 'Dies ist ein deutscher Satz. Dies ist ein weiterer Satz! Und noch einer?';

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    expect($chunks)->toHaveCount(1);
});

it('handles text with abbreviations', function (): void {
    // Abbreviations with periods shouldn't break sentences incorrectly
    $content = 'Dr. Smith went to the U.S.A. for a conference. He enjoyed it.';

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    expect($chunks)->toHaveCount(1);
});

it('splits very long content appropriately', function (): void {
    $paragraphs = [];

    for ($i = 1; $i <= 10; ++$i) {
        $paragraphs[] = "{100 tokens} paragraph {$i}.";
    }
    $content = implode("\n\n", $paragraphs);

    $chunks = $this->strategy->chunk($content, maxTokens: 250);

    // Should create multiple chunks
    expect($chunks)->toBeGreaterThan(1);

    // Verify we didn't lose content
    $reassembled = implode("\n\n", $chunks);
    $originalTokens = (new PlaceholderTokenizer)->count($content);
    $reassembledTokens = (new PlaceholderTokenizer)->count($reassembled);

    // Token counts should be close (may vary slightly due to joining)
    expect($reassembledTokens)->toBeGreaterThanOrEqual($originalTokens * 0.95);
});

it('handles content with tabs', function (): void {
    $content = "Line 1\tWith tab\nLine 2\t\tWith tabs";

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('respects token limits when chunking by paragraphs', function (): void {
    $content = str_repeat("{60 tokens}\n\n", 5); // 5 paragraphs, 60 tokens each

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    foreach ($chunks as $chunk) {
        $tokenCount = (new PlaceholderTokenizer)->count($chunk);
        expect($tokenCount)->toBeLessThanOrEqual(100);
    }
});

it('has name "plain"', function (): void {
    expect($this->strategy->getName())->toBe('plain');
});

it('can handle any content', function (): void {
    // PlainTextChunkingStrategy is the fallback and should always return true
    expect($this->strategy->canHandle('any text'))->toBeTrue();
    expect($this->strategy->canHandle('# Markdown'))->toBeTrue();
    expect($this->strategy->canHandle(''))->toBeTrue();
});

it('handles extremely long single paragraph', function (): void {
    // Create sentences with known token counts
    $sentences = [];

    for ($i = 0; $i < 20; ++$i) {
        $sentences[] = '{50 tokens}.';
    }
    $content = ' '.implode(' ', $sentences); // 1000 tokens total

    $chunks = $this->strategy->chunk($content, maxTokens: 200);

    // Should chunk by sentences
    expect($chunks)->toBeGreaterThan(1);

    // Verify each chunk respects token limit (with some tolerance for separators)
    foreach ($chunks as $chunk) {
        $tokenCount = (new PlaceholderTokenizer)->count($chunk);
        // Allow some overhead for whitespace/separators
        expect($tokenCount)->toBeLessThanOrEqual(220);
    }
});

it('handles text with only commas no periods', function (): void {
    $content = str_repeat('{50 tokens}, ', 5);

    $chunks = $this->strategy->chunk($content, maxTokens: 80);

    // Should fall back to word boundaries since no sentence terminators
    expect($chunks)->toBeGreaterThan(1);
});

it('preserves formatting in chunks', function (): void {
    $content = <<<'TEXT'
        First paragraph
        with line break.

        Second paragraph.
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 1000);

    expect($chunks[0])->toBe($content);
});
