<?php declare(strict_types=1);

use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\JsonSchema\Types\StringType;
use Mindtwo\AutoTranslatable\Services\StructuredTranslationAgent;

it('builds a string schema property for every field key', function (): void {
    $agent = new StructuredTranslationAgent('You are a translator.', ['name', 'subtitle', 'meta_title']);

    $schema = $agent->schema(new JsonSchemaTypeFactory);

    expect(array_keys($schema))->toBe(['name', 'subtitle', 'meta_title'])
        ->and($schema['name'])->toBeInstanceOf(StringType::class)
        ->and($schema['name']->toArray()['type'])->toBe('string');
});

it('reads provider and model from config', function (): void {
    config([
        'auto-translatable.provider' => 'anthropic',
        'auto-translatable.model' => 'claude-sonnet-4-5',
        'auto-translatable.request_timeout' => 321,
    ]);

    $agent = new StructuredTranslationAgent('You are a translator.', ['name']);

    expect($agent->provider())->toBe('anthropic')
        ->and($agent->model())->toBe('claude-sonnet-4-5')
        ->and($agent->timeout())->toBe(321);
});
