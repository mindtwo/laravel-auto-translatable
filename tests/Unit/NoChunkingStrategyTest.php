<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\NoChunkingStrategy;

beforeEach(function (): void {
    $this->strategy = new NoChunkingStrategy;
});

it('returns content as single chunk regardless of size', function (): void {
    $content = 'This is a test content that should not be chunked.';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('returns large content as single chunk', function (): void {
    $content = str_repeat('This is a very long text that would normally exceed token limits. ', 100);

    $chunks = $this->strategy->chunk($content, maxTokens: 10);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('returns empty string as single chunk', function (): void {
    $content = '';

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe('');
});

it('preserves multiline content exactly', function (): void {
    $content = <<<'TEXT'
        Line 1
        Line 2
        Line 3

        Line 5 after blank line
        TEXT;

    $chunks = $this->strategy->chunk($content, maxTokens: 100);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('preserves markdown content without parsing', function (): void {
    $content = <<<'MD'
        # Heading

        **Bold** text and *italic* text.

        ```php
        function test() {
            return true;
        }
        ```

        [Link](https://example.com)
        MD;

    $chunks = $this->strategy->chunk($content, maxTokens: 50);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('preserves special characters and unicode', function (): void {
    $content = 'Special chars: äöü ñ é 中文 한글 العربية emoji: 🎉🚀';

    $chunks = $this->strategy->chunk($content, maxTokens: 10);

    expect($chunks)
        ->toHaveCount(1)
        ->and($chunks[0])->toBe($content);
});

it('has name "none"', function (): void {
    expect($this->strategy->getName())->toBe('none');
});

it('returns false for canHandle', function (): void {
    // NoChunkingStrategy never auto-detects - must be explicitly requested
    expect($this->strategy->canHandle('any content'))->toBeFalse();
    expect($this->strategy->canHandle('# Markdown'))->toBeFalse();
    expect($this->strategy->canHandle(''))->toBeFalse();
});
