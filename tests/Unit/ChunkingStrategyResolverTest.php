<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\MarkdownChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\NoChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategies\PlainTextChunkingStrategy;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategyResolver;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;

beforeEach(function (): void {
    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
    $this->resolver = resolve(ChunkingStrategyResolver::class);
});

it('auto-detects markdown content', function (): void {
    $content = <<<'MD'
        # Heading

        This is markdown content with **bold** text.
        MD;

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(MarkdownChunkingStrategy::class);
});

it('auto-detects markdown with code blocks', function (): void {
    $content = <<<'MD'
        ```php
        function test() {}
        ```
        MD;

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(MarkdownChunkingStrategy::class);
});

it('auto-detects markdown with links', function (): void {
    $content = 'Check out [this link](https://example.com) for more info.';

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(MarkdownChunkingStrategy::class);
});

it('falls back to plain text for non-markdown content', function (): void {
    $content = 'This is just plain text without any markdown syntax.';

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(PlainTextChunkingStrategy::class);
});

it('falls back to plain text for empty content', function (): void {
    $content = '';

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(PlainTextChunkingStrategy::class);
});

it('resolves explicit markdown strategy', function (): void {
    $content = 'Plain text content';

    $strategy = $this->resolver->resolve($content, 'markdown');

    expect($strategy)->toBeInstanceOf(MarkdownChunkingStrategy::class);
});

it('resolves explicit plain strategy', function (): void {
    $content = '# Markdown content';

    $strategy = $this->resolver->resolve($content, 'plain');

    expect($strategy)->toBeInstanceOf(PlainTextChunkingStrategy::class);
});

it('resolves explicit none strategy', function (): void {
    $content = 'Any content';

    $strategy = $this->resolver->resolve($content, 'none');

    expect($strategy)->toBeInstanceOf(NoChunkingStrategy::class);
});

it('throws exception for unknown strategy name', function (): void {
    $this->resolver->resolve('content', 'unknown');
})->throws(InvalidArgumentException::class, 'Unknown chunking strategy: unknown');

it('defaults to auto detection when strategy is null', function (): void {
    $markdownContent = '# Heading';
    $strategy = $this->resolver->resolve($markdownContent, null);

    expect($strategy)->toBeInstanceOf(MarkdownChunkingStrategy::class);

    $plainContent = 'Plain text';
    $strategy = $this->resolver->resolve($plainContent, null);

    expect($strategy)->toBeInstanceOf(PlainTextChunkingStrategy::class);
});

it('uses plain text for content without markdown indicators', function (): void {
    $content = <<<'TEXT'
        This is plain text.
        It has multiple lines.
        But no markdown syntax.
        TEXT;

    $strategy = $this->resolver->resolve($content, 'auto');

    expect($strategy)->toBeInstanceOf(PlainTextChunkingStrategy::class);
});
