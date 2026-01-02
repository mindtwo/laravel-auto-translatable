<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Set up config
    config([
        'auto-translatable.chunk_size' => 3000,
        'auto-translatable.output_tokens' => 100000,
    ]);
    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
});

it('translates simple content without chunking', function (): void {
    $sourceContent = "# Hello World\n\nThis is a test.";
    $expectedTranslation = "# Hallo Welt\n\nDas ist ein Test.";

    // Fake Prism response
    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText($expectedTranslation)
            ->withUsage(new Usage(10, 20)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)->toBe($expectedTranslation)
        ->and($result->source_locale)->toBe('en')
        ->and($result->target_locale)->toBe('de')
        ->and($result->chunks_count)->toBe(1);

    // Assert Prism was called correctly
    $fake->assertCallCount(1);
});

it('translates large content with multiple chunks', function (): void {
    // Create content that will be chunked (> 3000 tokens ≈ 10,500 chars)
    $sourceContent = "# Large Document\n\n{2995 tokens}\n\nEnd of document.";
    $chunk1Translation = "# Großes Dokument\n\nTest Inhalt";
    $chunk2Translation = "Test Inhalt\n\n Ende des Dokuments.";

    // Fake responses for both chunks
    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText($chunk1Translation)
            ->withUsage(new Usage(1500, 1500)),
        TextResponseFake::make()
            ->withText($chunk2Translation)
            ->withUsage(new Usage(1500, 1500)),
    ]);

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->chunks_count)->toBeGreaterThan(1)
        ->and($result->translated_content)->toContain('# Großes Dokument')
        ->and($result->translated_content)->toContain('Test Inhalt')
        ->and($result->translated_content)->toContain('Ende des Dokuments.');

    // Assert Prism was called for each chunk
    $fake->assertCallCount($result->chunks_count);
});
