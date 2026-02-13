<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Drivers;

use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\DTOs\SubscriberList;
use LaravelPlus\Subscribe\DTOs\SyncResult;

final class MailchimpProvider extends AbstractProvider
{
    protected function getBaseUrl(): string
    {
        $serverPrefix = $this->config['server_prefix'] ?? 'us1';

        return "https://{$serverPrefix}.api.mailchimp.com/3.0";
    }

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return SyncResult::failure('List ID is required for Mailchimp');
        }

        $subscriberHash = md5(mb_strtolower($subscriber->email));

        $payload = [
            'email_address' => $subscriber->email,
            'status' => $subscriber->status === 'pending' ? 'pending' : 'subscribed',
            'merge_fields' => array_filter([
                'FNAME' => $subscriber->firstName,
                'LNAME' => $subscriber->lastName,
                'PHONE' => $subscriber->phone,
                'COMPANY' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
        ];

        if ($subscriber->tags) {
            $payload['tags'] = $subscriber->tags;
        }

        $response = $this->makeRequest(
            'put',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}",
            $payload
        );

        if ($response['success']) {
            return SyncResult::success(
                message: 'Subscriber added successfully',
                providerId: $response['body']['id'] ?? $subscriberHash,
            );
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to add subscriber',
            errorCode: (string) ($response['body']['status'] ?? $response['status']),
            data: $response['body'],
        );
    }

    public function unsubscribe(string $email, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return SyncResult::failure('List ID is required for Mailchimp');
        }

        $subscriberHash = md5(mb_strtolower($email));

        $response = $this->makeRequest(
            'patch',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}",
            ['status' => 'unsubscribed']
        );

        if ($response['success']) {
            return SyncResult::success('Unsubscribed successfully');
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to unsubscribe',
            errorCode: (string) $response['status'],
        );
    }

    public function update(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return SyncResult::failure('List ID is required for Mailchimp');
        }

        $subscriberHash = md5(mb_strtolower($subscriber->email));

        $payload = [
            'merge_fields' => array_filter([
                'FNAME' => $subscriber->firstName,
                'LNAME' => $subscriber->lastName,
                'PHONE' => $subscriber->phone,
                'COMPANY' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
        ];

        $response = $this->makeRequest(
            'patch',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}",
            $payload
        );

        if ($response['success']) {
            return SyncResult::success('Subscriber updated successfully');
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to update subscriber',
            errorCode: (string) $response['status'],
        );
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return false;
        }

        $subscriberHash = md5(mb_strtolower($email));

        $response = $this->makeRequest(
            'get',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}"
        );

        if (!$response['success']) {
            return false;
        }

        return ($response['body']['status'] ?? '') === 'subscribed';
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return null;
        }

        $subscriberHash = md5(mb_strtolower($email));

        $response = $this->makeRequest(
            'get',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}"
        );

        if (!$response['success']) {
            return null;
        }

        $member = $response['body'];
        $mergeFields = $member['merge_fields'] ?? [];

        return Subscriber::fromArray([
            'email' => $member['email_address'],
            'first_name' => $mergeFields['FNAME'] ?? null,
            'last_name' => $mergeFields['LNAME'] ?? null,
            'phone' => $mergeFields['PHONE'] ?? null,
            'company' => $mergeFields['COMPANY'] ?? null,
            'attributes' => $mergeFields,
            'tags' => array_column($member['tags'] ?? [], 'name'),
            'lists' => [$listId],
            'ip_address' => $member['ip_signup'] ?? null,
            'status' => $member['status'],
            'provider_id' => $member['id'],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->makeRequest('get', "{$this->getBaseUrl()}/lists", ['count' => 100]);

        if (!$response['success']) {
            return [];
        }

        return array_map(
            fn ($list) => SubscriberList::fromArray([
                'id' => $list['id'],
                'name' => $list['name'],
                'subscriber_count' => $list['stats']['member_count'] ?? 0,
                'provider_id' => $list['id'],
                'double_opt_in' => $list['double_optin'] ?? true,
            ]),
            $response['body']['lists'] ?? []
        );
    }

    public function createList(SubscriberList $list): SyncResult
    {
        $payload = [
            'name' => $list->name,
            'contact' => [
                'company' => $this->config['company'] ?? '',
                'address1' => $this->config['address1'] ?? '',
                'city' => $this->config['city'] ?? '',
                'state' => $this->config['state'] ?? '',
                'zip' => $this->config['zip'] ?? '',
                'country' => $this->config['country'] ?? 'US',
            ],
            'permission_reminder' => $this->config['permission_reminder'] ?? 'You signed up for updates.',
            'campaign_defaults' => [
                'from_name' => $this->config['from_name'] ?? config('app.name'),
                'from_email' => $this->config['from_email'] ?? config('mail.from.address'),
                'subject' => '',
                'language' => 'en',
            ],
            'email_type_option' => true,
            'double_optin' => $list->doubleOptIn,
        ];

        $response = $this->makeRequest('post', "{$this->getBaseUrl()}/lists", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'List created successfully',
                providerId: $response['body']['id'],
            );
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to create list',
            errorCode: (string) $response['status'],
        );
    }

    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return SyncResult::failure('List ID is required for Mailchimp');
        }

        $subscriberHash = md5(mb_strtolower($email));

        $payload = [
            'tags' => array_map(fn ($tag) => ['name' => $tag, 'status' => 'active'], $tags),
        ];

        $response = $this->makeRequest(
            'post',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}/tags",
            $payload
        );

        if ($response['success'] || $response['status'] === 204) {
            return SyncResult::success('Tags added successfully');
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to add tags',
            errorCode: (string) $response['status'],
        );
    }

    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        if (!$listId) {
            return SyncResult::failure('List ID is required for Mailchimp');
        }

        $subscriberHash = md5(mb_strtolower($email));

        $payload = [
            'tags' => array_map(fn ($tag) => ['name' => $tag, 'status' => 'inactive'], $tags),
        ];

        $response = $this->makeRequest(
            'post',
            "{$this->getBaseUrl()}/lists/{$listId}/members/{$subscriberHash}/tags",
            $payload
        );

        if ($response['success'] || $response['status'] === 204) {
            return SyncResult::success('Tags removed successfully');
        }

        return SyncResult::failure(
            message: $response['body']['detail'] ?? 'Failed to remove tags',
            errorCode: (string) $response['status'],
        );
    }

    public function getName(): string
    {
        return 'mailchimp';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Basic '.base64_encode('anystring:'.$this->config['api_key']),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
