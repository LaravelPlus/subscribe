<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests;

use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('subscribe.default', 'database');
    }
}
