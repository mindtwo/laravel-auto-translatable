<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mindtwo\AutoTranslatable\Adapters\SpatieTranslatableAdapter;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Tests\Support\SpatieArticle;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('articles', function (Blueprint $table): void {
        $table->id();
        $table->json('title');
        $table->json('content');
        $table->timestamps();
    });

    // Configure adapter
    config([
        'auto-translatable.adapter' => SpatieTranslatableAdapter::class,
        'auto-translatable.available_locales' => ['en', 'de', 'fr'],
        'auto-translatable.default_source_locale' => 'en',
    ]);
});

it('supports models with spatie HasTranslations trait', function (): void {
    $adapter = new SpatieTranslatableAdapter;
    $article = new SpatieArticle;
    expect($adapter->supports($article))->toBeTrue();
});

it('does not support models without HasTranslations trait', function (): void {
    $adapter = new SpatieTranslatableAdapter;
    $regularModel = new class extends Model {};
    expect($adapter->supports($regularModel))->toBeFalse();
});

it('gets available locales from config', function (): void {
    $adapter = new SpatieTranslatableAdapter;
    $article = new SpatieArticle;
    $locales = $adapter->getAvailableLocales($article);
    expect($locales)->toBe(['en', 'de', 'fr']);
});

it('gets source locale from config', function (): void {
    $adapter = new SpatieTranslatableAdapter;
    $article = new SpatieArticle;

    $sourceLocale = $adapter->getSourceLocale($article);
    expect($sourceLocale)->toBe('en');
});

it('gets field value for specific locale', function (): void {
    $adapter = new SpatieTranslatableAdapter;

    $article = SpatieArticle::query()->create([
        'title' => ['en' => 'Hello World', 'de' => 'Hallo Welt'],
        'content' => ['en' => 'This is content', 'de' => 'Das ist Inhalt'],
    ]);

    $enContent = $adapter->getFieldValue($article, 'content', 'en');
    $deContent = $adapter->getFieldValue($article, 'content', 'de');

    expect($enContent)->toBe('This is content')->and($deContent)->toBe('Das ist Inhalt');
});

it('returns null for field value when model is not supported', function (): void {
    $adapter = new SpatieTranslatableAdapter;

    $regularModel = new class extends Model {};

    $value = $adapter->getFieldValue($regularModel, 'content', 'en');

    expect($value)->toBeNull();
});

it('applies translations to model', function (): void {
    $adapter = new SpatieTranslatableAdapter;

    $article = SpatieArticle::query()->create([
        'title' => ['en' => 'Test Article'],
        'content' => ['en' => 'Original English content'],
    ]);

    // Create translation results
    $results = collect([
        new TranslationResult([
            'field_name' => 'title',
            'target_locale' => 'de',
            'translated_content' => 'Deutscher Titel',
            'status' => TranslationStatus::COMPLETED,
        ]),
        new TranslationResult([
            'field_name' => 'title',
            'target_locale' => 'fr',
            'translated_content' => 'Should be skipped',
            'status' => TranslationStatus::PENDING,
        ]),
        new TranslationResult([
            'translatable_type' => $article->getMorphClass(),
            'translatable_id' => $article->id,
            'field_name' => 'content',
            'source_locale' => 'en',
            'target_locale' => 'de',
            'source_content' => 'Original English content',
            'translated_content' => 'Ursprünglicher englischer Inhalt',
            'status' => TranslationStatus::COMPLETED,
        ]),
        new TranslationResult([
            'translatable_type' => $article->getMorphClass(),
            'translatable_id' => $article->id,
            'field_name' => 'content',
            'source_locale' => 'en',
            'target_locale' => 'fr',
            'source_content' => 'Original English content',
            'translated_content' => 'Contenu anglais original',
            'status' => TranslationStatus::COMPLETED,
        ]),
    ]);

    $adapter->applyTranslations($article, $results);

    expect($article->getTranslation('title', 'de'))->toBe('Deutscher Titel')
        // FR was PENDING, so it should fall back to default locale
        ->and($article->getTranslation('title', 'fr'))->toBe('Test Article')
        ->and($article->getTranslation('content', 'en'))->toBe('Original English content')
        ->and($article->getTranslation('content', 'de'))->toBe('Ursprünglicher englischer Inhalt')
        ->and($article->getTranslation('content', 'fr'))->toBe('Contenu anglais original');
});

it('throws exception when applying translations to unsupported model', function (): void {
    $adapter = new SpatieTranslatableAdapter;

    $regularModel = new class extends Model {
        protected $table = 'regular_models';
    };

    $results = collect([
        new TranslationResult([
            'field_name' => 'content',
            'target_locale' => 'de',
            'translated_content' => 'Test',
            'status' => TranslationStatus::COMPLETED,
        ]),
    ]);

    expect(fn () => $adapter->applyTranslations($regularModel, $results))
        ->toThrow(InvalidArgumentException::class);
});
