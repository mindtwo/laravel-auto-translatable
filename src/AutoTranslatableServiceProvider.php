<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable;

use Illuminate\Support\Facades\Event;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;
use Mindtwo\AutoTranslatable\Listeners\ApplyTranslationToModel;
use Mindtwo\AutoTranslatable\PostProcessors\LinkReplacer;
use Mindtwo\AutoTranslatable\Services\ChunkingStrategyResolver;
use Mindtwo\AutoTranslatable\Services\Markdown\TikTokenizer;
use Mindtwo\AutoTranslatable\Services\Markdown\TokenEstimator;
use Mindtwo\AutoTranslatable\Services\Markdown\Tokenizer;
use Mindtwo\AutoTranslatable\Services\TranslationProvider;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;
use Yethee\Tiktoken\EncoderProvider;

class AutoTranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-auto-translatable')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_translation_results_table');
    }

    public function packageRegistered(): void
    {
        // Register translatable adapter if configured
        if (config('auto-translatable.adapter') !== null) {
            $this->app->singleton(TranslatableAdapter::class, function () {
                $adapterClass = config('auto-translatable.adapter');

                return new $adapterClass;
            });
        }

        // Register translation service
        $this->app->singleton(TranslationService::class, fn ($app) => new TranslationService(
            $app->make(ChunkingStrategyResolver::class),
            $app->make(TranslationProvider::class),
        ));

        // Register translation service
        $this->app->singleton(Tokenizer::class, function () {
            $model = config('auto-translatable.model');

            // Try to use TikToken if available for OpenAI models
            if ($model && class_exists(EncoderProvider::class)) {
                try {
                    return new TikTokenizer($model);
                } catch (Throwable) {
                    // Model not supported by TikToken or other error, fall back to estimator
                }
            }

            // Fall back to character-based estimation
            return new TokenEstimator;
        });

        // Register link replacer if enabled
        $this->app->singleton('auto-translatable.link-replacer', function () {
            if (! config('auto-translatable.link_replacement.enabled')) {
                return;
            }

            return new LinkReplacer;
        });
    }

    public function packageBooted(): void
    {
        Event::listen(ModelTranslationCompleted::class, ApplyTranslationToModel::class);
    }
}
