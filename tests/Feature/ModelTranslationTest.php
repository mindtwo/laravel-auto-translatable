<?php declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Events\TranslationFailed;
use Mindtwo\AutoTranslatable\Jobs\TranslateContent;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Services\TranslationProvider;
use Mindtwo\AutoTranslatable\Tests\Support\SpatieArticle;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create articles table
    Schema::create('articles', function ($table): void {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->timestamps();
    });

    // Configure chunking
    config([
        'auto-translatable.chunk_size' => 500,
        'auto-translatable.output_tokens' => 100000,
        'auto-translatable.available_locales' => ['en', 'de'],
        'auto-translatable.default_source_locale' => 'en',
        'auto-translatable.queue_translations' => false,
        'auto-translatable.auto_apply' => true,
    ]);
});

it('translates a model with markdown content requiring chunking', function (): void {
    // Create article with large markdown content that will be chunked
    $largeMarkdown = file_get_contents(__DIR__.'/../Fixtures/long-en.md');

    $article = new SpatieArticle;
    $article->title = 'Laravel Tutorial';
    $article->content = $largeMarkdown;
    $article->save();

    // Expected translations for chunks (German)
    $chunk1Translation = file_get_contents(__DIR__.'/../Fixtures/long-de-1.md');
    $chunk2Translation = file_get_contents(__DIR__.'/../Fixtures/long-de-2.md');

    // Set up Prism fake responses for multiple chunks
    $fake = Prism::fake([
        TextResponseFake::make()
            ->withText($chunk1Translation)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(1500, 1600)),
        TextResponseFake::make()
            ->withText($chunk2Translation)
            ->withFinishReason(FinishReason::Stop)
            ->withUsage(new Usage(1400, 1500)),
    ]);

    // Execute translation
    $article->autoTranslate();
    $article->refresh(); // Refresh the model to get the applied translations
    $result = $article->translationResults()->first();

    // Verify the translation result
    expect($result)->toBeInstanceOf(TranslationResult::class)
        ->and($result->status)->toBe(TranslationStatus::COMPLETED)
        ->and($result->source_locale)->toBe('en')
        ->and($result->target_locale)->toBe('de')
        ->and($result->chunks_count)->toBe(2);

    // Verify the combined translation matches expected output
    $combinedTranslation = $article->getTranslation('content', 'de');

    expect($combinedTranslation)
        // Check German translations are present
        ->toContain('# Vollständiges Laravel-Tutorial')
        ->toContain('Webanwendungs-Framework')
        ->toContain('## Konfiguration')
        ->toContain('## Modelle')
        ->toContain('## Bereitstellung')
        // Verify code blocks are preserved
        ->toContain('```bash')
        ->toContain('composer create-project laravel/laravel example-app')
        ->toContain('```php')
        ->toContain('Route::get(\'/welcome\', function ()')
        ->toContain('class UserController extends Controller')
        ->toContain('```env')
        ->toContain('DB_CONNECTION=mysql')
        // Verify links are preserved
        ->toContain('[Laravel-Dokumentation](https://laravel.com/docs)')
        // Verify inline code is preserved
        ->toContain('`APP_ENV=production`')
        ->toContain('`php artisan config:cache`');

    expect($article->getTranslationResult('content', 'de')->id)
        ->toBe($result->id);

    // Verify Prism was called twice (once per chunk)
    $fake->assertCallCount(2);
})->group('model');

it('translates model with job execution', function (): void {
    Queue::fake();
    config([
        'auto-translatable.available_locales' => ['en', 'de', 'fr'],
        'auto-translatable.queue_translations' => true,
    ]);

    $markdown = <<<'MD'
        # Testing Job Execution

        This is a test article with markdown content including:

        - Lists
        - **Bold text**
        - `Inline code`

        ```php
        function example() {
            return true;
        }
        ```
        MD;

    $article = SpatieArticle::query()->create([
        'title' => 'Test Article',
        'content' => $markdown,
    ]);

    // Trigger auto-translation
    $article->autoTranslate();

    // Assert jobs were dispatched
    Queue::assertPushed(TranslateContent::class, 2); // de and fr
});

it('handles translation failure and dispatches TranslationFailed event', function (): void {
    Event::fake([TranslationFailed::class]);

    $article = SpatieArticle::query()->create([
        'title' => 'Test Article',
        'content' => '# Simple Test Content',
    ]);

    // Mock the TranslationProvider to throw an exception
    $mockProvider = Mockery::mock(TranslationProvider::class);
    $mockProvider->shouldReceive('translateChunk')
        ->andThrow(new PrismException('API rate limit exceeded'));

    app()->instance(TranslationProvider::class, $mockProvider);

    // Execute translation
    $article->autoTranslate();

    // Verify TranslationFailed event was dispatched
    Event::assertDispatched(
        TranslationFailed::class,
        fn (TranslationFailed $event) => $event->model->id === $article->id
            && $event->field === 'content'
            && $event->error === 'API rate limit exceeded',
    );

    // Verify the TranslationResult is marked as failed
    $result = $article->translationResults()->first();

    expect($result)->toBeInstanceOf(TranslationResult::class)
        ->and($result->status)->toBe(TranslationStatus::FAILED)
        ->and($result->source_locale)->toBe('en')
        ->and($result->target_locale)->toBe('de')
        ->and($result->error_message)->toBe('API rate limit exceeded')
        ->and($result->translated_content)->toBeNull();

    // Verify the failed translation was NOT applied to the model
    // When translation fails, Spatie falls back to the source locale
    expect($article->getTranslation('content', 'de'))->toBe('# Simple Test Content');
});
