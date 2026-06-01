<?php declare(strict_types=1);

namespace Mindtwo\AutoTranslatable\Tests;

use Laravel\Ai\AiServiceProvider;
use Mindtwo\AutoTranslatable\AutoTranslatableServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [AiServiceProvider::class, AutoTranslatableServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('queue.default', 'sync');
    }
}
