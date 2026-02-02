<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Drivers;

use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;

final class BrevoProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.brevo.com/v3';

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $listId = $listId ?? $this->getDefaultListId();

        $payload = [
            'email' => $subscriber->email,
            'attributes' => array_filter([
                'FIRSTNAME' => $subscriber->firstName,
                'LASTNAME' => $subscriber->lastName,
                'SMS' => $subscriber->phone,
                'COMPANY' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
            'updateEnabled' => true,
        ];

        if ($listId) {
            $payload['listIds'] = [(int) $listId];
        }

        $response = $this->makeRequest('post', "{$this->baseUrl}/contacts", $payload);

        if ($response['success'] || $response['status'] === 204) {
            return SyncResult::success(
                message: 'Subscriber added successfully',
                providerId: $subscriber->email,
            );
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to add subscriber',
            errorCode: (string) $response['status'],
            data: $response['body'],
        );
    }

    public function unsubscribe(string $email, ?string $listId = null): SyncResult
    {
        if ($listId) {
            $response = $this->makeRequest(
                'post',
                "{$this->baseUrl}/contacts/lists/{$listId}/contacts/remove",
                ['emails' => [$email]]
            );
        } else {
            $response = $this->makeRequest(
                'put',
                "{$this->baseUrl}/contacts/{$email}",
                ['emailBlacklisted' => true]
            );
        }

        if ($response['success'] || $response['status'] === 204) {
            return SyncResult::success('Unsubscribed successfully');
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to unsubscribe',
            errorCode: (string) $response['status'],
        );
    }

    public function update(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $payload = [
            'attributes' => array_filter([
                'FIRSTNAME' => $subscriber->firstName,
                'LASTNAME' => $subscriber->lastName,
                'SMS' => $subscriber->phone,
                'COMPANY' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
        ];

        $response = $this->makeRequest('put', "{$this->baseUrl}/contacts/{$subscriber->email}", $payload);

        if ($response['success'] || $response['status'] === 204) {
            return SyncResult::success('Subscriber updated successfully');
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to update subscriber',
            errorCode: (string) $response['status'],
        );
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/contacts/{$email}");

        if (!$response['success']) {
            return false;
        }

        $contact = $response['body'];

        if ($contact['emailBlacklisted'] ?? false) {
            return false;
        }

        if ($listId) {
            return in_array((int) $listId, $contact['listIds'] ?? []);
        }

        return true;
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/contacts/{$email}");

        if (!$response['success']) {
            return null;
        }

        $contact = $response['body'];
        $attributes = $contact['attributes'] ?? [];

        return Subscriber::fromArray([
            'email' => $contact['email'],
            'first_name' => $attributes['FIRSTNAME'] ?? null,
            'last_name' => $attributes['LASTNAME'] ?? null,
            'phone' => $attributes['SMS'] ?? null,
            'company' => $attributes['COMPANY'] ?? null,
            'attributes' => $attributes,
            'lists' => array_map('strval', $contact['listIds'] ?? []),
            'status' => ($contact['emailBlacklisted'] ?? false) ? 'unsubscribed' : 'subscribed',
            'provider_id' => (string) $contact['id'],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/contacts/lists", ['limit' => 50]);

        if (!$response['success']) {
            return [];
        }

        return array_map(
            fn ($list) => SubscriberList::fromArray([
                'id' => (string) $list['id'],
                'name' => $list['name'],
                'subscriber_count' => $list['totalSubscribers'] ?? 0,
                'provider_id' => (string) $list['id'],
            ]),
            $response['body']['lists'] ?? []
        );
    }

    public function createList(SubscriberList $list): SyncResult
    {
        $payload = [
            'name' => $list->name,
            'folderId' => (int) ($this->config['folder_id'] ?? 1),
        ];

        $response = $this->makeRequest('post', "{$this->baseUrl}/contacts/lists", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'List created successfully',
                providerId: (string) $response['body']['id'],
            );
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to create list',
            errorCode: (string) $response['status'],
        );
    }

    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        return SyncResult::failure('Brevo uses lists instead of tags');
    }

    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        return SyncResult::failure('Brevo uses lists instead of tags');
    }

    public function getName(): string
    {
        return 'brevo';
    }

    protected function getHeaders(): array
    {
        return [
            'api-key' => $this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
