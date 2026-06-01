<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Services\TranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;

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

    // Capture every prompt sent to the provider so we can assert the request count
    $prompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$prompts, $expectedTranslation): string {
        $prompts[] = $prompt;

        return $expectedTranslation;
    });

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->translated_content)->toBe($expectedTranslation)
        ->and($result->source_locale)->toBe('en')
        ->and($result->target_locale)->toBe('de')
        ->and($result->chunks_count)->toBe(1);

    // Short content is not chunked: exactly one provider request carrying the source
    expect($prompts)->toHaveCount(1)
        ->and($prompts[0])->toContain('Hello World');
});

it('translates large content with multiple chunks', function (): void {
    // Create content that will be chunked (> 3000 tokens ≈ 10,500 chars)
    $sourceContent = "# Large Document\n\n{2995 tokens}\n\nEnd of document.";
    $chunk1Translation = "# Großes Dokument\n\nTest Inhalt";
    $chunk2Translation = "Test Inhalt\n\n Ende des Dokuments.";

    // Capture every prompt so we can assert one provider request was made per chunk
    $prompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$prompts, $chunk1Translation, $chunk2Translation): string {
        $prompts[] = $prompt;

        return count($prompts) === 1 ? $chunk1Translation : $chunk2Translation;
    });

    $service = app(TranslationService::class);
    $result = $service->translate($sourceContent, 'en', 'de');

    expect($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->chunks_count)->toBeGreaterThan(1)
        ->and($result->translated_content)->toContain('# Großes Dokument')
        ->and($result->translated_content)->toContain('Test Inhalt')
        ->and($result->translated_content)->toContain('Ende des Dokuments.');

    // The content was chunked: one provider request per chunk
    expect($prompts)->toHaveCount($result->chunks_count);
});
