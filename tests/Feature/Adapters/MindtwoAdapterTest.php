<?php declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mindtwo\AutoTranslatable\Adapters\MindtwoTranslatableAdapter;
use Mindtwo\AutoTranslatable\Enums\TranslationStatus;
use Mindtwo\AutoTranslatable\Models\TranslationResult;
use Mindtwo\AutoTranslatable\Tests\Support\MindtwoArticle;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::create('mindtwo_articles', function (Blueprint $table): void {
        $table->id();
        $table->string('title');
        $table->string('content');
        $table->timestamps();
    });

    Schema::create('translatable', function (Blueprint $table): void {
        $table->id();
        $table->uuid()->unique();
        $table->morphs('translatable');
        $table->string('key', 75);
        $table->string('locale', 5);
        $table->text('text');
        $table->timestamps();
        $table->index(['locale', 'translatable_type', 'translatable_id']);
        $table->unique(['locale', 'key', 'translatable_type', 'translatable_id']);
    });

    // Configure adapter
    config([
        'auto-translatable.adapter' => MindtwoTranslatableAdapter::class,
        'auto-translatable.available_locales' => ['en', 'de', 'fr'],
        'auto-translatable.default_source_locale' => 'en',
    ]);
});

it('supports models with mindtwo HasTranslations trait', function (): void {
    $adapter = new MindtwoTranslatableAdapter;
    $article = new MindtwoArticle;
    expect($adapter->supports($article))->toBeTrue();
});

it('does not support models without HasTranslations trait', function (): void {
    $adapter = new MindtwoTranslatableAdapter;
    $regularModel = new class extends Model {};
    expect($adapter->supports($regularModel))->toBeFalse();
});

it('gets available locales from config', function (): void {
    $adapter = new MindtwoTranslatableAdapter;
    $article = new MindtwoArticle;
    $locales = $adapter->getAvailableLocales($article);
    expect($locales)->toBe(['en', 'de', 'fr']);
});

it('gets source locale from model default locale', function (): void {
    $adapter = new MindtwoTranslatableAdapter;
    $article = new MindtwoArticle;

    // Mock the defaultLocaleOnModel method
    $article = new class extends MindtwoArticle {
        public function defaultLocaleOnModel(): string
        {
            return 'en';
        }
    };

    $sourceLocale = $adapter->getSourceLocale($article);
    expect($sourceLocale)->toBe('en');
});

it('gets field value for specific locale', function (): void {
    $adapter = new MindtwoTranslatableAdapter;
    $article = MindtwoArticle::query()->create(['title' => 'Hello World', 'content' => 'This is content']);
    $article->setTranslations([
        'title' => 'Hallo Welt',
        'content' => 'Das ist Inhalt',
    ], 'de');

    $enContent = $adapter->getFieldValue($article, 'content', 'en');
    $deContent = $adapter->getFieldValue($article, 'content', 'de');

    expect($enContent)->toBe('This is content')->and($deContent)->toBe('Das ist Inhalt');
});

it('returns null for field value when model is not supported', function (): void {
    $adapter = new MindtwoTranslatableAdapter;

    $regularModel = new class extends Model {};

    $value = $adapter->getFieldValue($regularModel, 'content', 'en');

    expect($value)->toBeNull();
});

it('applies translations to model', function (): void {
    $adapter = new MindtwoTranslatableAdapter;

    $article = MindtwoArticle::query()->create(['title' => 'Test Article', 'content' => 'Original English content']);

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
    $adapter = new MindtwoTranslatableAdapter;

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
