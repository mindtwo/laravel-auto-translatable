<?php declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Mindtwo\AutoTranslatable\Enums\TranslationApiCallKind;
use Mindtwo\AutoTranslatable\Events\TranslationApiCallCompleted;
use Mindtwo\AutoTranslatable\Services\StructuredTranslationAgent;
use Mindtwo\AutoTranslatable\Services\TranslationAgent;
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

it('dispatches TranslationApiCallCompleted after a single-content chunk call', function (): void {
    Event::fake([TranslationApiCallCompleted::class]);

    // Return an explicit TextResponse so we can pin the Usage the event surfaces.
    TranslationAgent::fake(fn (): TextResponse => new TextResponse(
        'Hallo Welt',
        new Usage(promptTokens: 120, completionTokens: 30, cacheReadInputTokens: 80),
        new Meta('anthropic', 'claude-sonnet-4-5'),
    ));

    (new TranslationProvider)->translateChunk('Hello world', 'en', 'de', []);

    Event::assertDispatched(
        TranslationApiCallCompleted::class,
        fn (TranslationApiCallCompleted $event): bool => $event->kind === TranslationApiCallKind::Chunk
            && $event->sourceLocale === 'en'
            && $event->targetLocale === 'de'
            && $event->fieldKeys === []
            && $event->provider === 'anthropic'
            && $event->model === 'claude-sonnet-4-5'
            && $event->usage->promptTokens === 120
            && $event->usage->completionTokens === 30
            && $event->usage->cacheReadInputTokens === 80,
    );
});

it('dispatches TranslationApiCallCompleted after a batched fields call', function (): void {
    Event::fake([TranslationApiCallCompleted::class]);

    StructuredTranslationAgent::fake(fn (): StructuredTextResponse => new StructuredTextResponse(
        ['name' => 'Roter Stuhl', 'subtitle' => 'Bequem'],
        json_encode(['name' => 'Roter Stuhl', 'subtitle' => 'Bequem']),
        new Usage(promptTokens: 400, completionTokens: 50),
        new Meta('anthropic', 'claude-sonnet-4-5'),
    ));

    (new TranslationProvider)->translateFields(
        ['name' => 'Red Chair', 'subtitle' => 'Comfortable'],
        'en',
        'de',
        [],
    );

    Event::assertDispatched(
        TranslationApiCallCompleted::class,
        fn (TranslationApiCallCompleted $event): bool => $event->kind === TranslationApiCallKind::Fields
            && $event->sourceLocale === 'en'
            && $event->targetLocale === 'de'
            && $event->fieldKeys === ['name', 'subtitle']
            && $event->provider === 'anthropic'
            && $event->model === 'claude-sonnet-4-5'
            && $event->usage->promptTokens === 400
            && $event->usage->completionTokens === 50,
    );
});
