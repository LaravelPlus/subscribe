<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests;

use LaravelPlus\Subscribe\SubscribeServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SubscribeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('subscribe.default', 'database');
        $app->afterResolving('migrator', fn ($migrator) => $migrator->path(__DIR__.'/database/migrations'));
    }
}
