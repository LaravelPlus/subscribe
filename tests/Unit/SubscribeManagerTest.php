<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Tests\Unit;

use LaravelPlus\Subscribe\Drivers\BrevoProvider;
use LaravelPlus\Subscribe\Drivers\DatabaseProvider;
use LaravelPlus\Subscribe\Drivers\HubSpotProvider;
use LaravelPlus\Subscribe\Drivers\MailchimpProvider;
use LaravelPlus\Subscribe\SubscribeManager;
use LaravelPlus\Subscribe\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class SubscribeManagerTest extends TestCase
{
    private SubscribeManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('subscribe.providers.mailchimp', [
            'api_key' => 'test',
            'server_prefix' => 'us1',
        ]);

        $this->app['config']->set('subscribe.providers.brevo', [
            'api_key' => 'test',
        ]);

        $this->app['config']->set('subscribe.providers.hubspot', [
            'api_key' => 'test',
        ]);

        $this->manager = $this->app->make(SubscribeManager::class);
    }

    #[Test]
    public function test_get_default_driver_returns_database(): void
    {
        $this->assertSame('database', $this->manager->getDefaultDriver());
    }

    #[Test]
    public function test_create_database_driver_returns_correct_class(): void
    {
        $driver = $this->manager->driver('database');

        $this->assertInstanceOf(DatabaseProvider::class, $driver);
    }

    #[Test]
    public function test_create_mailchimp_driver_returns_correct_class(): void
    {
        $driver = $this->manager->driver('mailchimp');

        $this->assertInstanceOf(MailchimpProvider::class, $driver);
    }

    #[Test]
    public function test_create_brevo_driver_returns_correct_class(): void
    {
        $driver = $this->manager->driver('brevo');

        $this->assertInstanceOf(BrevoProvider::class, $driver);
    }

    #[Test]
    public function test_create_hubspot_driver_returns_correct_class(): void
    {
        $driver = $this->manager->driver('hubspot');

        $this->assertInstanceOf(HubSpotProvider::class, $driver);
    }

    #[Test]
    public function test_provider_alias_returns_same_as_driver(): void
    {
        $driverInstance = $this->manager->driver('database');
        $providerInstance = $this->manager->provider('database');

        $this->assertSame(get_class($driverInstance), get_class($providerInstance));
    }
}
