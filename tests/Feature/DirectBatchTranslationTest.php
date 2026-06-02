<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Services\StructuredTranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'auto-translatable.chunk_size' => 3000,
        'auto-translatable.output_tokens' => 100000,
    ]);
    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
});

it('translates a keyed set of strings in a single request', function (): void {
    $prompts = [];
    StructuredTranslationAgent::fake(function (string $prompt) use (&$prompts): array {
        $prompts[] = $prompt;

        return [
            'name' => 'Roter Stuhl',
            'subtitle' => 'Bequem und langlebig',
        ];
    });

    $results = app(TranslationService::class)->translateMany(
        ['name' => 'Red Chair', 'subtitle' => 'Comfortable and durable'],
        'en',
        'de',
    );

    expect($results)->toHaveCount(2)
        ->and($results['name']->status)->toBe(TranslationStatus::COMPLETED)
        ->and($results['name']->translated_content)->toBe('Roter Stuhl')
        ->and($results['name']->metadata['batched'])->toBeTrue()
        ->and($results['subtitle']->translated_content)->toBe('Bequem und langlebig');

    expect($prompts)->toHaveCount(1);
});

it('splits large sets across multiple structured requests', function (): void {
    config(['auto-translatable.batch_max_fields' => 2]);

    $prompts = [];
    StructuredTranslationAgent::fake(function (string $prompt) use (&$prompts): array {
        $prompts[] = $prompt;

        return [
            'a' => 'A-de', 'b' => 'B-de', 'c' => 'C-de', 'd' => 'D-de', 'e' => 'E-de',
        ];
    });

    $results = app(TranslationService::class)->translateMany(
        ['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E'],
        'en',
        'de',
    );

    expect($results)->toHaveCount(5)
        ->and($results['a']->translated_content)->toBe('A-de')
        ->and($results['e']->translated_content)->toBe('E-de');

    // Five strings capped at two per request -> three structured requests.
    expect($prompts)->toHaveCount(3);
});

it('recovers keys missing from the structured response', function (): void {
    StructuredTranslationAgent::fake(fn (): array => ['name' => 'Roter Stuhl']);

    $chunkPrompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$chunkPrompts): string {
        $chunkPrompts[] = $prompt;

        return 'Bequem und langlebig';
    });

    $results = app(TranslationService::class)->translateMany(
        ['name' => 'Red Chair', 'subtitle' => 'Comfortable and durable'],
        'en',
        'de',
    );

    expect($results['name']->translated_content)->toBe('Roter Stuhl')
        ->and($results['name']->metadata['batched'])->toBeTrue()
        ->and($results['subtitle']->status)->toBe(TranslationStatus::COMPLETED)
        ->and($results['subtitle']->translated_content)->toBe('Bequem und langlebig');

    // Only the missing key required a dedicated request.
    expect($chunkPrompts)->toHaveCount(1)
        ->and($chunkPrompts[0])->toContain('Comfortable and durable');
});
