<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Drivers;

use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\DTOs\SubscriberList;
use LaravelPlus\Subscribe\DTOs\SyncResult;

final class MailerLiteProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://connect.mailerlite.com/api';

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $payload = [
            'email' => $subscriber->email,
            'fields' => array_filter([
                'name' => $subscriber->firstName,
                'last_name' => $subscriber->lastName,
                'phone' => $subscriber->phone,
                'company' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
            'status' => $subscriber->status === 'pending' ? 'unconfirmed' : 'active',
        ];

        if ($listId) {
            $payload['groups'] = [$listId];
        }

        $response = $this->makeRequest('post', "{$this->baseUrl}/subscribers", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'Subscriber added successfully',
                providerId: $response['body']['data']['id'] ?? '',
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
        $subscriberId = $this->getSubscriberId($email);

        if (!$subscriberId) {
            return SyncResult::failure('Subscriber not found');
        }

        if ($listId) {
            $response = $this->makeRequest(
                'delete',
                "{$this->baseUrl}/subscribers/{$subscriberId}/groups/{$listId}"
            );
        } else {
            $response = $this->makeRequest(
                'put',
                "{$this->baseUrl}/subscribers/{$subscriberId}",
                ['status' => 'unsubscribed']
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
        $subscriberId = $this->getSubscriberId($subscriber->email);

        if (!$subscriberId) {
            return SyncResult::failure('Subscriber not found');
        }

        $payload = [
            'fields' => array_filter([
                'name' => $subscriber->firstName,
                'last_name' => $subscriber->lastName,
                'phone' => $subscriber->phone,
                'company' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
        ];

        $response = $this->makeRequest('put', "{$this->baseUrl}/subscribers/{$subscriberId}", $payload);

        if ($response['success']) {
            return SyncResult::success('Subscriber updated successfully');
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to update subscriber',
            errorCode: (string) $response['status'],
        );
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $subscriber = $this->getSubscriberData($email);

        if (!$subscriber) {
            return false;
        }

        if ($subscriber['status'] !== 'active') {
            return false;
        }

        if ($listId) {
            $groups = array_column($subscriber['groups'] ?? [], 'id');

            return in_array($listId, $groups);
        }

        return true;
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $subscriber = $this->getSubscriberData($email);

        if (!$subscriber) {
            return null;
        }

        $fields = $subscriber['fields'] ?? [];

        return Subscriber::fromArray([
            'email' => $subscriber['email'],
            'first_name' => $fields['name'] ?? null,
            'last_name' => $fields['last_name'] ?? null,
            'phone' => $fields['phone'] ?? null,
            'company' => $fields['company'] ?? null,
            'attributes' => $fields,
            'lists' => array_column($subscriber['groups'] ?? [], 'id'),
            'ip_address' => $subscriber['ip_address'] ?? null,
            'status' => $subscriber['status'] === 'active' ? 'subscribed' : $subscriber['status'],
            'provider_id' => $subscriber['id'],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/groups", ['limit' => 100]);

        if (!$response['success']) {
            return [];
        }

        return array_map(
            fn ($group) => SubscriberList::fromArray([
                'id' => $group['id'],
                'name' => $group['name'],
                'subscriber_count' => $group['active_count'] ?? 0,
                'provider_id' => $group['id'],
            ]),
            $response['body']['data'] ?? []
        );
    }

    public function createList(SubscriberList $list): SyncResult
    {
        $payload = [
            'name' => $list->name,
        ];

        $response = $this->makeRequest('post', "{$this->baseUrl}/groups", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'List created successfully',
                providerId: $response['body']['data']['id'],
            );
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to create list',
            errorCode: (string) $response['status'],
        );
    }

    public function getName(): string
    {
        return 'mailerlite';
    }

    protected function getDefaultListId(): ?string
    {
        return $this->config['default_group_id'] ?? null;
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getSubscriberData(string $email): ?array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/subscribers/{$email}");

        if (!$response['success']) {
            return null;
        }

        return $response['body']['data'] ?? null;
    }

    private function getSubscriberId(string $email): ?string
    {
        $subscriber = $this->getSubscriberData($email);

        return $subscriber ? $subscriber['id'] : null;
    }
}
