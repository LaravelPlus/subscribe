---
name: subscribe-development
description: >-
  Activate when working with the nejcc/subscribe package — using the Subscribe
  facade, managing subscribers, subscription lists, email providers, double
  opt-in flows, or building admin pages under admin/subscribers.
---

# Subscribe Development

Package: `nejcc/subscribe` — Location: `packages/nejcc/subscribe/`
Namespace: `Nejcc\Subscribe` — Facade: `Subscribe` — Config: `subscribe`

## When to Apply

- Using `Subscribe::` facade methods
- Creating or modifying subscribe providers, controllers, models, or migrations in the package
- Working with subscribers, subscription lists, tags, or double opt-in flows
- Integrating with external email providers (Brevo, Mailchimp, HubSpot, ConvertKit, MailerLite)
- Building admin UI pages under `admin/subscribers`
- Writing tests for subscribe functionality

## Manager Pattern

Uses Laravel's `Manager` class via `SubscribeManager`:

<code-snippet name="Subscribe Facade Usage" lang="php">
use Nejcc\Subscribe\Facades\Subscribe;

// Default provider (from config)
Subscribe::subscribe($subscriberDto);
Subscribe::isSubscribed('user@example.com');

// Specific provider
Subscribe::provider('mailchimp')->subscribe($subscriber, $listId);
Subscribe::driver('brevo')->getLists();

// Tag management
Subscribe::addTags('user@example.com', ['vip', 'early-access']);
Subscribe::removeTags('user@example.com', ['trial']);

// List management
Subscribe::getLists();
Subscribe::createList($subscriberListDto);
</code-snippet>

## Core Contract — `SubscribeProviderContract`

Every provider implements:
- `subscribe(Subscriber $subscriber, ?string $listId)` — Subscribe to a list
- `unsubscribe(string $email, ?string $listId)` — Unsubscribe from a list
- `update(Subscriber $subscriber, ?string $listId)` — Update subscriber info
- `isSubscribed(string $email, ?string $listId)` — Check subscription status
- `getSubscriber(string $email, ?string $listId)` — Get subscriber details
- `getLists()` — Get all lists/audiences
- `createList(SubscriberList $list)` — Create a new list
- `addTags(string $email, array $tags, ?string $listId)` — Add tags
- `removeTags(string $email, array $tags, ?string $listId)` — Remove tags
- `getName()` — Provider name

## Available Providers

`database` (default), `brevo`, `mailchimp`, `hubspot`, `convertkit`, `mailerlite`

Config: `subscribe.default` (env: `SUBSCRIBE_PROVIDER`, default: `database`).

All concrete providers extend `AbstractProvider` which provides config handling and a `makeRequest()` HTTP utility.

### Creating a New Provider

<code-snippet name="New Subscribe Provider" lang="php">
// src/Drivers/MyProvider.php
final class MyProvider extends AbstractProvider
{
    public function getName(): string { return 'my_provider'; }

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        // Implement subscription logic
        return SyncResult::success('Subscribed', $providerId);
    }

    // ... implement remaining contract methods
}

// Register in SubscribeManager:
public function createMyproviderDriver(): SubscribeProviderContract
{
    return new MyProvider($this->config->get('subscribe.providers.my_provider', []));
}
</code-snippet>

## Models

| Model | Table | Key Fields | Key Methods |
|-------|-------|------------|-------------|
| `Subscriber` | `subscribers` | email, first_name, last_name, phone, company, attributes, tags, source, ip_address, status, confirmed_at, confirmation_token, provider, provider_id | `isSubscribed()`, `isConfirmed()`, `confirm()`, `unsubscribe()`, `addTag()`, `removeTag()`, `hasTag()`, `getFullNameAttribute()` |
| `SubscriptionList` | `subscription_lists` | name, slug, description, is_public, is_default, double_opt_in, welcome_email_enabled, welcome_email_subject/content, confirmation_email_subject/content, provider, provider_id | `activeSubscribers()`, `getDefault()`, `requiresDoubleOptIn()`, `hasWelcomeEmail()` |

Pivot table: `subscriber_list`. Both models have factories.

**Subscriber scopes:** `subscribed()`, `unsubscribed()`, `pending()`, `confirmed()`, `unconfirmed()`, `inList($listId)`, `withTag($tag)`

**SubscriptionList scopes:** `public()`, `default()`

## DTOs

- **`Subscriber`** — readonly: email, firstName, lastName, phone, company, attributes (array), tags (array), lists (array), source, ipAddress, status, providerId. Methods: `toArray()`, `fromArray()`, `getFullName()`
- **`SubscriberList`** — readonly: name, id, description, providerId, subscriberCount, isPublic, doubleOptIn. Methods: `toArray()`, `fromArray()`
- **`SyncResult`** — readonly: success (bool), message, providerId, errorCode, data (array). Static: `success()`, `failure()`. Method: `toArray()`

## Double Opt-In Flow

Configured via `subscribe.double_opt_in` (default: `true`):

1. Subscriber created with `confirmation_token`
2. `ConfirmationEmailSent` event dispatched
3. User clicks confirmation link
4. `confirm()` sets `confirmed_at`, clears token
5. `SubscriptionConfirmed` event dispatched

Tokens expire after `subscribe.confirmation.expire_hours` (configurable).

## Admin Routes

Prefix: `admin/subscribers` — Middleware: `['web', 'auth', 'role:super-admin,admin']`

- Dashboard: `GET /`, `GET /stats`
- Subscribers: `GET /subscribers`, `GET /subscribers/export`, `GET /subscribers/{subscriber}`, `PUT /subscribers/{subscriber}`, `DELETE /subscribers/{subscriber}`, `POST /subscribers/{subscriber}/confirm`, `POST /subscribers/{subscriber}/resend`
- Lists: `GET /lists`, `POST /lists`, `GET /lists/{list}`, `PUT /lists/{list}`, `DELETE /lists/{list}`

## Events

| Event | Payload | When |
|-------|---------|------|
| `Subscribed` | subscriber, listId | Email subscribes to a list |
| `Unsubscribed` | subscriber, listId | Email unsubscribes from a list |
| `SubscriptionConfirmed` | subscriber | Email confirmation completed |
| `ConfirmationEmailSent` | subscriber | Confirmation email dispatched |
| `SubscriberUpdated` | subscriber | Subscriber details updated |

## Configuration

Key config paths in `subscribe.php`:

| Key | Default | Description |
|-----|---------|-------------|
| `default` | `database` (env: `SUBSCRIBE_PROVIDER`) | Default provider |
| `providers.*` | — | Per-provider credentials (api_key, list_id, etc.) |
| `double_opt_in` | `true` | Require email confirmation |
| `confirmation.*` | — | Email settings (from, subject, expire_hours) |
| `welcome_email` | `true` | Send welcome after confirmation |
| `sync.enabled` | — | Auto-sync with external providers |
| `sync.queue` | — | Queue sync jobs |
| `rate_limit.enabled` | — | Anti-abuse rate limiting |
| `rate_limit.max_attempts` | `5` | Max subscribe attempts |
| `rate_limit.decay_minutes` | `60` | Rate limit window |
| `routes.prefix` | `subscribe` | Public route prefix |
| `admin.enabled` | `true` | Enable admin panel routes |
| `admin.prefix` | `admin/subscribers` | Admin route prefix |
| `admin.middleware` | `['web', 'auth', 'role:super-admin,admin']` | Admin middleware |

## Conventions

- `declare(strict_types=1)` and `final class` on all concrete classes
- Form Request classes for validation (never inline)
- `Builder` return types on scope methods
- Config-driven admin route prefix and middleware
- `AdminNavigation` registry for sidebar items
- Factory classes for all models
- DTOs for data transfer between layers
- Events dispatched for significant state changes

## Common Pitfalls

- Double opt-in is **enabled by default** — new subscribers require email confirmation before they are active
- The `database` provider stores subscribers locally; external providers (Brevo, Mailchimp, etc.) sync to third-party APIs
- Tag operations return "not supported" on providers that don't implement them — check the provider first
- Rate limiting is configurable but defaults to 5 attempts per 60 minutes per IP
- The `subscriber_list` pivot table links subscribers to lists — use the BelongsToMany relationship, not manual queries
- Provider driver names in `SubscribeManager` factory methods are lowercase: `createBrevoDriver()`, `createMailchimpDriver()`, etc.
