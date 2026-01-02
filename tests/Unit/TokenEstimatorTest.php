<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\Markdown\MarkdownChunker;

describe('tokenizer', function (): void {
    it('uses TikToken for OpenAI models when available', function (): void {
        config(['auto-translatable.model' => 'gpt-4']);
        $chunker = resolve(MarkdownChunker::class);

        expect($chunker->estimateTokens('Hello World'))->toBe(2);
        expect($chunker->estimateTokens('function test() { return true; }'))->toBe(8);
        expect($chunker->estimateTokens('こんにちは'))->toBe(1);
    });

    it('uses TokenEstimator for non-OpenAI models', function (): void {
        config(['auto-translatable.model' => 'claude-3-5-sonnet-20241022']);
        $chunker = resolve(MarkdownChunker::class);

        expect($chunker->estimateTokens('Hello World'))->toBe(4);
        expect($chunker->estimateTokens('function test() { return true; }'))->toBe(10);
        expect($chunker->estimateTokens('こんにちは'))->toBe(5);
    })->group('estimator');
});
