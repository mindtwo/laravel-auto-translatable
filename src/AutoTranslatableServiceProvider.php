<?php

namespace Mindtwo\AutoTranslatable;

use Illuminate\Support\Facades\Event;
use Mindtwo\AutoTranslatable\Contracts\TranslatableAdapter;
use Mindtwo\AutoTranslatable\Contracts\TranslationProvider;
use Mindtwo\AutoTranslatable\Events\ModelTranslationCompleted;
use Mindtwo\AutoTranslatable\Listeners\ApplyTranslationToModel;
use Mindtwo\AutoTranslatable\PostProcessors\LinkReplacer;
use Mindtwo\AutoTranslatable\Services\MarkdownChunker;
use Mindtwo\AutoTranslatable\Services\TranslationService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class AutoTranslatableServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-auto-translatable')
            ->hasConfigFile()
            ->hasMigration('create_translation_results_table');
    }

    public function packageRegistered(): void
    {
        // Register the translation provider
        $this->app->singleton(TranslationProvider::class, function ($app) {
            $providerName = config('auto-translatable.default_provider');
            $providerConfig = config("auto-translatable.providers.{$providerName}");

            $driverClass = $providerConfig['driver'];

            return new $driverClass($providerConfig);
        });

        if (config('auto-translatable.adapter') !== null) {
            $this->app->singleton(TranslatableAdapter::class, function () {
                $adapterClass = config('auto-translatable.adapter');

                return new $adapterClass();
            });
        }

        // Register markdown chunker
        $this->app->singleton(MarkdownChunker::class, function ($app) {
            return new MarkdownChunker(
                config('auto-translatable.chunk_size', 3000)
            );
        });

        // Register translation service
        $this->app->singleton(TranslationService::class, function ($app) {
            return new TranslationService(
                $app->make(TranslationProvider::class),
                $app->make(MarkdownChunker::class)
            );
        });

        // Register link replacer if enabled
        $this->app->singleton('auto-translatable.link-replacer', function () {
            if (! config('auto-translatable.link_replacement.enabled')) {
                return null;
            }

            return new LinkReplacer();
        });
    }

    public function packageBooted(): void
    {
        if (config('auto-translatable.auto_apply', false)) {
            Event::listen(ModelTranslationCompleted::class, ApplyTranslationToModel::class);
        }
    }
}
