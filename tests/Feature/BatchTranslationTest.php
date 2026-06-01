<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Services\StructuredTranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationProvider;
use Mindtwo\AutoTranslatable\Tests\Support\PlaceholderTokenizer;
use Mindtwo\AutoTranslatable\Tests\Support\SpatieProduct;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('products', function ($table): void {
        $table->id();
        $table->string('name');
        $table->string('subtitle')->nullable();
        $table->text('short_description')->nullable();
        $table->string('meta_title')->nullable();
        $table->timestamps();
    });

    config([
        'auto-translatable.available_locales' => ['en', 'de'],
        'auto-translatable.default_source_locale' => 'en',
        'auto-translatable.queue_translations' => false,
        'auto-translatable.auto_apply' => true,
        'auto-translatable.batch_fields' => true,
        'auto-translatable.batch_max_tokens' => 100,
        'auto-translatable.chunk_size' => 3000,
    ]);

    $this->app->bind(Tokenizer::class, fn () => new PlaceholderTokenizer);
});

function makeProduct(): SpatieProduct
{
    $product = new SpatieProduct;
    $product->name = 'Red Chair';
    $product->subtitle = 'Comfortable and durable';
    $product->short_description = 'A great chair.';
    $product->meta_title = 'Buy the Red Chair';
    $product->save();

    return $product;
}

it('translates many short fields in a single batched request', function (): void {
    $structuredPrompts = [];
    StructuredTranslationAgent::fake(function (string $prompt) use (&$structuredPrompts): array {
        $structuredPrompts[] = $prompt;

        return [
            'name' => 'Roter Stuhl',
            'subtitle' => 'Bequem und langlebig',
            'short_description' => 'Ein toller Stuhl.',
            'meta_title' => 'Kaufen Sie den Roten Stuhl',
        ];
    });

    $chunkPrompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$chunkPrompts): string {
        $chunkPrompts[] = $prompt;

        return 'should-not-be-used';
    });

    $product = makeProduct();
    $product->autoTranslate();
    $product->refresh();

    $results = $product->translationResults()->get();

    expect($results)->toHaveCount(4)
        ->and($results->every(fn ($r) => $r->status === TranslationStatus::COMPLETED))->toBeTrue()
        ->and($results->every(fn ($r) => $r->chunks_count === 1))->toBeTrue()
        ->and($results->every(fn ($r) => $r->metadata['batched'] === true))->toBeTrue();

    expect($product->getTranslation('name', 'de'))->toBe('Roter Stuhl')
        ->and($product->getTranslation('meta_title', 'de'))->toBe('Kaufen Sie den Roten Stuhl');

    // One structured request for all four fields, and no per-field chunk requests.
    expect($structuredPrompts)->toHaveCount(1)
        ->and($chunkPrompts)->toHaveCount(0);
});

it('batches short fields but chunks large fields individually', function (): void {
    $structuredPrompts = [];
    StructuredTranslationAgent::fake(function (string $prompt) use (&$structuredPrompts): array {
        $structuredPrompts[] = $prompt;

        return [
            'name' => 'Roter Stuhl',
            'subtitle' => 'Bequem und langlebig',
            'meta_title' => 'Kaufen Sie den Roten Stuhl',
        ];
    });

    $chunkPrompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$chunkPrompts): string {
        $chunkPrompts[] = $prompt;

        return 'Eine sehr lange Beschreibung.';
    });

    $product = makeProduct();
    // Push short_description over the batch threshold so it must chunk.
    $product->short_description = 'A very long description. {500 tokens}';
    $product->save();

    $product->autoTranslate();
    $product->refresh();

    expect($product->getTranslation('name', 'de'))->toBe('Roter Stuhl')
        ->and($product->getTranslation('short_description', 'de'))->toBe('Eine sehr lange Beschreibung.');

    // Three small fields batched in one request; the large field translated on
    // its own per-field path.
    expect($structuredPrompts)->toHaveCount(1)
        ->and($chunkPrompts)->toHaveCount(1)
        ->and($chunkPrompts[0])->toContain('A very long description.');
});

it('falls back to per-field translation when a field is missing from the response', function (): void {
    StructuredTranslationAgent::fake(fn (): array => [
        'name' => 'Roter Stuhl',
        'subtitle' => 'Bequem und langlebig',
        'short_description' => 'Ein toller Stuhl.',
        // meta_title intentionally omitted
    ]);

    $chunkPrompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$chunkPrompts): string {
        $chunkPrompts[] = $prompt;

        return 'Kaufen Sie den Roten Stuhl';
    });

    $product = makeProduct();
    $product->autoTranslate();
    $product->refresh();

    $results = $product->translationResults()->get();

    expect($results)->toHaveCount(4)
        ->and($results->every(fn ($r) => $r->status === TranslationStatus::COMPLETED))->toBeTrue()
        ->and($product->getTranslation('meta_title', 'de'))->toBe('Kaufen Sie den Roten Stuhl');

    // Only the omitted field was recovered with a per-field request.
    expect($chunkPrompts)->toHaveCount(1)
        ->and($chunkPrompts[0])->toContain('Buy the Red Chair');
});

it('falls back to per-field translation when the batched request fails', function (): void {
    $mockProvider = Mockery::mock(TranslationProvider::class);
    $mockProvider->shouldReceive('translateFields')
        ->andThrow(new RuntimeException('structured output unavailable'));
    $mockProvider->shouldReceive('translateChunk')
        ->andReturn('FALLBACK');

    app()->instance(TranslationProvider::class, $mockProvider);

    $product = makeProduct();
    $product->autoTranslate();
    $product->refresh();

    $results = $product->translationResults()->get();

    expect($results)->toHaveCount(4)
        ->and($results->every(fn ($r) => $r->status === TranslationStatus::COMPLETED))->toBeTrue()
        ->and($results->every(fn ($r) => $r->translated_content === 'FALLBACK'))->toBeTrue();
});

it('does not batch when the feature is disabled', function (): void {
    config(['auto-translatable.batch_fields' => false]);

    StructuredTranslationAgent::fake(fn (): array => ['name' => 'nope']);

    $chunkPrompts = [];
    TranslationAgent::fake(function (string $prompt) use (&$chunkPrompts): string {
        $chunkPrompts[] = $prompt;

        return 'übersetzt';
    });

    $product = makeProduct();
    $product->autoTranslate();

    // Disabled: every field goes through its own per-field request.
    expect($chunkPrompts)->toHaveCount(4);
});
