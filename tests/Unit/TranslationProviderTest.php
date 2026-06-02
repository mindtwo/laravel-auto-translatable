<?php declare(strict_types=1);

use Mindtwo\AutoTranslatable\Services\StructuredTranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationProvider;

it('translates multiple fields in a single structured request', function (): void {
    $prompts = [];
    StructuredTranslationAgent::fake(function (string $prompt) use (&$prompts): array {
        $prompts[] = $prompt;

        return [
            'name' => 'Roter Stuhl',
            'subtitle' => 'Bequem und langlebig',
        ];
    });

    $translated = (new TranslationProvider)->translateFields(
        ['name' => 'Red Chair', 'subtitle' => 'Comfortable and durable'],
        'en',
        'de',
        [],
    );

    expect($translated)->toBe([
        'name' => 'Roter Stuhl',
        'subtitle' => 'Bequem und langlebig',
    ]);

    // All fields were translated with a single provider request that carried
    // every field's source content.
    expect($prompts)->toHaveCount(1)
        ->and($prompts[0])->toContain('Red Chair')
        ->and($prompts[0])->toContain('Comfortable and durable');
});

it('omits fields the model leaves out of the structured response', function (): void {
    StructuredTranslationAgent::fake(fn (): array => ['name' => 'Roter Stuhl']);

    $translated = (new TranslationProvider)->translateFields(
        ['name' => 'Red Chair', 'subtitle' => 'Comfortable and durable'],
        'en',
        'de',
        [],
    );

    expect($translated)->toBe(['name' => 'Roter Stuhl']);
});
