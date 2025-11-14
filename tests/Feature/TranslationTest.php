<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mindtwo\AutoTranslatable\Concerns\HasAutoTranslations;
use Mindtwo\AutoTranslatable\Models\TranslationResult;

uses(RefreshDatabase::class);

// Test model
class TestPost extends Model
{
    use HasAutoTranslations;

    protected $table = 'posts';

    protected $guarded = [];

    public function autoTranslatableFields(): array
    {
        return ['title', 'body'];
    }
}

beforeEach(function () {
    // Create test posts table
    Schema::create('posts', function ($table) {
        $table->id();
        $table->string('title');
        $table->text('body');
        $table->timestamps();
    });
});

it('dispatches translation jobs for all locales', function () {
    $post = TestPost::create([
        'title' => 'Test Title',
        'body' => 'Test Body',
    ]);

    // Mock the adapter to return locales
    $this->mock(\Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter::class)
        ->shouldReceive('getSourceLocale')
        ->andReturn('en')
        ->shouldReceive('getAvailableLocales')
        ->andReturn(['en', 'de', 'fr'])
        ->shouldReceive('getFieldValue')
        ->andReturn('Test Body');

    // Expect jobs to be dispatched (2 target locales: de and fr)
    \Queue::fake();

    $post->autoTranslate();

    \Queue::assertPushed(\Mindtwo\AutoTranslatable\Jobs\TranslateContent::class, 2);
});

it('has translation results relationship', function () {
    $post = TestPost::create([
        'title' => 'Test',
        'body' => 'Test',
    ]);

    TranslationResult::create([
        'translatable_type' => TestPost::class,
        'translatable_id' => $post->id,
        'field_name' => 'body',
        'source_locale' => 'de',
        'target_locale' => 'en',
        'source_content' => 'Test',
        'translated_content' => 'Translated',
        'status' => 'completed',
    ]);

    expect($post->translationResults)->toHaveCount(1);
    expect($post->translationResults->first()->translated_content)->toBe('Translated');
});
