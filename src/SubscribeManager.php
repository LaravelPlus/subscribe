<?php

declare(strict_types=1);

namespace Nejcc\Subscribe;

use Illuminate\Support\Manager;
use Nejcc\Subscribe\Contracts\SubscribeProviderContract;
use Nejcc\Subscribe\Drivers\BrevoProvider;
use Nejcc\Subscribe\Drivers\ConvertKitProvider;
use Nejcc\Subscribe\Drivers\DatabaseProvider;
use Nejcc\Subscribe\Drivers\HubSpotProvider;
use Nejcc\Subscribe\Drivers\MailchimpProvider;
use Nejcc\Subscribe\Drivers\MailerLiteProvider;

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
