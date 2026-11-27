# laravelplus/subscribe

Email subscriptions and newsletter lists for Laravel, stored locally and synced to a
mailing provider: Brevo, Mailchimp, HubSpot, ConvertKit, MailerLite, or the database only.

Requires PHP 8.4+ and Laravel 12 or 13.

## Install

```bash
composer require laravelplus/subscribe
php artisan migrate
```

The service provider is auto-discovered. Publish what you want to change:

```bash
php artisan vendor:publish --tag=subscribe-config
php artisan vendor:publish --tag=subscribe-views        # confirmation / welcome mails
php artisan vendor:publish --tag=subscribe-migrations   # only if you need to edit them
```

## Providers

Pick the provider with `SUBSCRIBE_PROVIDER` (`database` by default) and fill in its keys:

```env
SUBSCRIBE_PROVIDER=brevo
BREVO_API_KEY=
BREVO_DEFAULT_LIST_ID=
```

Every provider entry lives under `providers` in `config/subscribe.php`
(`MAILCHIMP_*`, `HUBSPOT_*`, `CONVERTKIT_*`, `MAILERLITE_*`). Subscribers are always kept
in the local `subscribers` table; with `sync.enabled` they are pushed to the provider too.
That push runs synchronously inside the request (the `sync.queue` setting is not wired up
yet), so a slow or failing provider shows up in the subscribe response.

Use a provider directly through the facade:

```php
use LaravelPlus\Subscribe\Facades\Subscribe;

Subscribe::isSubscribed('ana@example.com');
Subscribe::provider('mailchimp')->addTags('ana@example.com', ['beta']);
```

## Routes

Public, under `subscribe.routes.prefix` (default `subscribe`) with the `web` middleware:

| Method | URI | Name |
|---|---|---|
| POST | `/subscribe` | `subscribe.store` (`email`, optional `first_name`, `last_name`, `list_id` uuid) |
| GET | `/subscribe/confirm/{token}` | `subscribe.confirm` |
| GET | `/subscribe/unsubscribe/{token}` | `subscribe.unsubscribe.form` |
| POST | `/subscribe/unsubscribe` | `subscribe.unsubscribe` |

After confirming or unsubscribing the visitor is redirected to `redirects.*`.

### Admin console

On by default (`SUBSCRIBE_ADMIN_ENABLED`), under `admin/subscribers`. The pages are
Inertia components (`admin/subscribers/*`, `admin/subscribers/lists/*`), so the console
needs `inertiajs/inertia-laravel` and those pages in the host app.

Access goes through the `subscribe.admin` gate; anyone it refuses gets a 404. The default
gate allows users whose model has `hasAnyRole()` (spatie/laravel-permission) with the
`super-admin` or `admin` role and denies everyone else, so on a host without roles the
console stays closed until you define the gate:

```php
Gate::define('subscribe.admin', fn (User $user): bool => $user->is_admin);
```

## Double opt-in

New subscribers start as `pending` and receive a confirmation mail
(`confirmation.*`: sender, subject, `expire_hours`). Only the link in that mail confirms
them; an expired token is refused. The package ships a `WelcomeSubscriber` mailable but
does not send it; listen for `SubscriptionConfirmed` to send one.

## Events

| Event | When |
|---|---|
| `Subscribed` | a new subscriber is created and no confirmation is required |
| `ConfirmationEmailSent` | a confirmation mail is queued (new subscriber with double opt-in, or an admin resend) |
| `SubscriptionConfirmed` | the confirmation link is used |
| `SubscriberUpdated` | a subscriber's details change |
| `Unsubscribed` | a subscriber unsubscribes |

All live in `LaravelPlus\Subscribe\Events`.

## Testing

```bash
composer test
vendor/bin/pint --test
```

## License

MIT, see [LICENSE](LICENSE).
