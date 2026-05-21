<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable;

use Illuminate\Contracts\Foundation\Application;
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
use Mindtwo\AutoTranslatable\Support\Config;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Throwable;
use Yethee\Tiktoken\EncoderProvider;

class AutoTranslatableServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-auto-translatable')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasMigration('create_translation_results_table');
    }

    /**
     * Register the package services.
     */
    public function packageRegistered(): void
    {
        // Bind the configured translatable adapter as a singleton so the
        // container can resolve it for every Eloquent model translation.
        if (config('auto-translatable.adapter') !== null) {
            $this->app->singleton(TranslatableAdapter::class, function (): TranslatableAdapter {
                /** @var class-string<TranslatableAdapter> $adapterClass */
                $adapterClass = config('auto-translatable.adapter');

                return new $adapterClass;
            });
        }

        $this->app->singleton(
            TranslationService::class,
            fn (Application $app): TranslationService => new TranslationService(
                $app->make(ChunkingStrategyResolver::class),
                $app->make(TranslationProvider::class),
            ),
        );

        // Resolve a tokenizer based on the configured model. We prefer the
        // accurate TikToken encoder for OpenAI models and fall back to the
        // character based estimator for every other provider.
        $this->app->singleton(Tokenizer::class, function (): Tokenizer {
            $model = Config::string('auto-translatable.model');

            if ($model !== '' && class_exists(EncoderProvider::class)) {
                try {
                    return new TikTokenizer($model);
                } catch (Throwable) {
                    // The model is not supported by TikToken; the character
                    // based estimator is sufficient for chunking purposes.
                }
            }

            return new TokenEstimator;
        });

        // The link replacer is opt-in. When disabled the binding returns null
        // so the translation pipeline can skip the post-processor entirely.
        $this->app->singleton('auto-translatable.link-replacer', function (): ?LinkReplacer {
            if (! config('auto-translatable.link_replacement.enabled')) {
                return null;
            }

            return new LinkReplacer;
        });
    }

    /**
     * Bootstrap the package services.
     */
    public function packageBooted(): void
    {
        Event::listen(ModelTranslationCompleted::class, ApplyTranslationToModel::class);
    }
}
