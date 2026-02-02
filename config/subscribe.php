<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Provider
    |--------------------------------------------------------------------------
    |
    | The default email marketing provider to use when no provider is
    | explicitly specified.
    |
    */
    'default' => env('SUBSCRIBE_PROVIDER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Configure each email marketing provider with their API credentials.
    |
    */
    'providers' => [
        'database' => [
            'driver' => 'database',
        ],

        'brevo' => [
            'driver' => 'brevo',
            'api_key' => env('BREVO_API_KEY'),
            'default_list_id' => env('BREVO_DEFAULT_LIST_ID'),
        ],

        'mailchimp' => [
            'driver' => 'mailchimp',
            'api_key' => env('MAILCHIMP_API_KEY'),
            'server_prefix' => env('MAILCHIMP_SERVER_PREFIX'), // e.g., us19
            'default_list_id' => env('MAILCHIMP_DEFAULT_LIST_ID'),
        ],

        'hubspot' => [
            'driver' => 'hubspot',
            'api_key' => env('HUBSPOT_API_KEY'),
            'portal_id' => env('HUBSPOT_PORTAL_ID'),
        ],

        'convertkit' => [
            'driver' => 'convertkit',
            'api_key' => env('CONVERTKIT_API_KEY'),
            'api_secret' => env('CONVERTKIT_API_SECRET'),
            'default_form_id' => env('CONVERTKIT_DEFAULT_FORM_ID'),
        ],

        'mailerlite' => [
            'driver' => 'mailerlite',
            'api_key' => env('MAILERLITE_API_KEY'),
            'default_group_id' => env('MAILERLITE_DEFAULT_GROUP_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Double Opt-In
    |--------------------------------------------------------------------------
    |
    | Whether to require email confirmation before adding subscribers.
    |
    */
    'double_opt_in' => env('SUBSCRIBE_DOUBLE_OPT_IN', true),

    /*
    |--------------------------------------------------------------------------
    | Confirmation Email
    |--------------------------------------------------------------------------
    |
    | Settings for the confirmation email sent during double opt-in.
    |
    */
    'confirmation' => [
        'from_name' => env('SUBSCRIBE_FROM_NAME', config('app.name')),
        'from_email' => env('SUBSCRIBE_FROM_EMAIL', config('mail.from.address')),
        'subject' => env('SUBSCRIBE_CONFIRMATION_SUBJECT', 'Please confirm your subscription'),
        'expire_hours' => env('SUBSCRIBE_CONFIRMATION_EXPIRE', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Welcome Email
    |--------------------------------------------------------------------------
    |
    | Send a welcome email after subscription is confirmed.
    |
    */
    'welcome_email' => [
        'enabled' => env('SUBSCRIBE_WELCOME_EMAIL', true),
        'subject' => env('SUBSCRIBE_WELCOME_SUBJECT', 'Welcome to our newsletter!'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    |
    | Settings for syncing subscribers with external providers.
    |
    */
    'sync' => [
        // Enable syncing with external providers
        'enabled' => env('SUBSCRIBE_SYNC_ENABLED', true),

        // Automatically sync new subscribers to external providers
        'auto_sync' => env('SUBSCRIBE_AUTO_SYNC', true),

        // Queue the sync job instead of running synchronously
        'queue' => env('SUBSCRIBE_SYNC_QUEUE', true),

        // Queue name for sync jobs
        'queue_name' => env('SUBSCRIBE_QUEUE_NAME', 'default'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    |
    | URLs to redirect to after various actions.
    |
    */
    'redirects' => [
        'confirmed' => env('SUBSCRIBE_REDIRECT_CONFIRMED', '/'),
        'unsubscribed' => env('SUBSCRIBE_REDIRECT_UNSUBSCRIBED', '/'),
        'invalid_token' => env('SUBSCRIBE_REDIRECT_INVALID', '/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Lists / Groups
    |--------------------------------------------------------------------------
    |
    | Define your subscription lists that map to provider-specific lists.
    |
    */
    'lists' => [
        'newsletter' => [
            'name' => 'Newsletter',
            'description' => 'General newsletter and updates',
            'brevo_list_id' => env('BREVO_NEWSLETTER_LIST_ID'),
            'mailchimp_list_id' => env('MAILCHIMP_NEWSLETTER_LIST_ID'),
            'hubspot_list_id' => env('HUBSPOT_NEWSLETTER_LIST_ID'),
        ],
        'product_updates' => [
            'name' => 'Product Updates',
            'description' => 'New features and product announcements',
            'brevo_list_id' => env('BREVO_PRODUCT_LIST_ID'),
            'mailchimp_list_id' => env('MAILCHIMP_PRODUCT_LIST_ID'),
            'hubspot_list_id' => env('HUBSPOT_PRODUCT_LIST_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limit subscription attempts to prevent abuse.
    |
    */
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 5,
        'decay_minutes' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Tables
    |--------------------------------------------------------------------------
    |
    | The names of the database tables used by the package.
    |
    */
    'tables' => [
        'subscribers' => 'subscribers',
        'subscription_lists' => 'subscription_lists',
        'subscriber_list' => 'subscriber_list',
    ],

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    |
    | Configure the subscription routes.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'subscribe',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Panel
    |--------------------------------------------------------------------------
    |
    | Configure the admin panel for managing subscribers.
    |
    */
    'admin' => [
        'enabled' => env('SUBSCRIBE_ADMIN_ENABLED', true),
        'prefix' => env('SUBSCRIBE_ADMIN_PREFIX', 'admin/subscribers'),
        'middleware' => ['web', 'auth'],
    ],
];
