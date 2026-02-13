<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe;

use Illuminate\Support\Manager;
use LaravelPlus\Subscribe\Contracts\SubscribeProviderContract;
use LaravelPlus\Subscribe\Drivers\BrevoProvider;
use LaravelPlus\Subscribe\Drivers\ConvertKitProvider;
use LaravelPlus\Subscribe\Drivers\DatabaseProvider;
use LaravelPlus\Subscribe\Drivers\HubSpotProvider;
use LaravelPlus\Subscribe\Drivers\MailchimpProvider;
use LaravelPlus\Subscribe\Drivers\MailerLiteProvider;

final class SubscribeManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('subscribe.default', 'database');
    }

    public function createDatabaseDriver(): SubscribeProviderContract
    {
        return new DatabaseProvider(
            $this->config->get('subscribe.providers.database', [])
        );
    }

    public function createBrevoDriver(): SubscribeProviderContract
    {
        return new BrevoProvider(
            $this->config->get('subscribe.providers.brevo', [])
        );
    }

    public function createMailchimpDriver(): SubscribeProviderContract
    {
        return new MailchimpProvider(
            $this->config->get('subscribe.providers.mailchimp', [])
        );
    }

    public function createHubspotDriver(): SubscribeProviderContract
    {
        return new HubSpotProvider(
            $this->config->get('subscribe.providers.hubspot', [])
        );
    }

    public function createConvertkitDriver(): SubscribeProviderContract
    {
        return new ConvertKitProvider(
            $this->config->get('subscribe.providers.convertkit', [])
        );
    }

    public function createMailerliteDriver(): SubscribeProviderContract
    {
        return new MailerLiteProvider(
            $this->config->get('subscribe.providers.mailerlite', [])
        );
    }

    public function provider(?string $name = null): SubscribeProviderContract
    {
        return $this->driver($name);
    }
}
