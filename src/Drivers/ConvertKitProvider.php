<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Drivers;

use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;

final class ConvertKitProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.convertkit.com/v3';

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $formId = $listId ?? $this->getDefaultListId();

        if (!$formId) {
            return SyncResult::failure('Form ID is required for ConvertKit');
        }

        $payload = [
            'api_key' => $this->config['api_key'],
            'email' => $subscriber->email,
            'first_name' => $subscriber->firstName,
            'fields' => array_filter([
                'last_name' => $subscriber->lastName,
                'phone' => $subscriber->phone,
                'company' => $subscriber->company,
                ...$subscriber->attributes,
            ]),
        ];

        if ($subscriber->tags) {
            $payload['tags'] = $subscriber->tags;
        }

        $response = $this->makeRequest('post', "{$this->baseUrl}/forms/{$formId}/subscribe", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'Subscriber added successfully',
                providerId: (string) ($response['body']['subscription']['subscriber']['id'] ?? ''),
            );
        }

        return SyncResult::failure(
            message: $response['body']['error'] ?? $response['body']['message'] ?? 'Failed to add subscriber',
            errorCode: (string) $response['status'],
            data: $response['body'],
        );
    }

    public function unsubscribe(string $email, ?string $listId = null): SyncResult
    {
        $payload = [
            'api_secret' => $this->config['api_secret'],
            'email' => $email,
        ];

        $response = $this->makeRequest('put', "{$this->baseUrl}/unsubscribe", $payload);

        if ($response['success']) {
            return SyncResult::success('Unsubscribed successfully');
        }

        return SyncResult::failure(
            message: $response['body']['error'] ?? 'Failed to unsubscribe',
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
            'api_secret' => $this->config['api_secret'],
            'first_name' => $subscriber->firstName,
            'fields' => array_filter([
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
            message: $response['body']['error'] ?? 'Failed to update subscriber',
            errorCode: (string) $response['status'],
        );
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $subscriber = $this->getSubscriberByEmail($email);

        if (!$subscriber) {
            return false;
        }

        return $subscriber['state'] === 'active';
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $subscriber = $this->getSubscriberByEmail($email);

        if (!$subscriber) {
            return null;
        }

        $fields = $subscriber['fields'] ?? [];

        return Subscriber::fromArray([
            'email' => $subscriber['email_address'],
            'first_name' => $subscriber['first_name'] ?? null,
            'last_name' => $fields['last_name'] ?? null,
            'phone' => $fields['phone'] ?? null,
            'company' => $fields['company'] ?? null,
            'attributes' => $fields,
            'status' => $subscriber['state'] === 'active' ? 'subscribed' : 'unsubscribed',
            'provider_id' => (string) $subscriber['id'],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/forms", [
            'api_key' => $this->config['api_key'],
        ]);

        if (!$response['success']) {
            return [];
        }

        return array_map(
            fn ($form) => SubscriberList::fromArray([
                'id' => (string) $form['id'],
                'name' => $form['name'],
                'subscriber_count' => $form['total_subscriptions'] ?? 0,
                'provider_id' => (string) $form['id'],
            ]),
            $response['body']['forms'] ?? []
        );
    }

    public function createList(SubscriberList $list): SyncResult
    {
        return SyncResult::failure('ConvertKit forms must be created through the dashboard');
    }

    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $errors = [];

        foreach ($tags as $tag) {
            $tagId = $this->getOrCreateTag($tag);

            if (!$tagId) {
                $errors[] = "Failed to find or create tag: {$tag}";

                continue;
            }

            $payload = [
                'api_key' => $this->config['api_key'],
                'email' => $email,
            ];

            $response = $this->makeRequest('post', "{$this->baseUrl}/tags/{$tagId}/subscribe", $payload);

            if (!$response['success']) {
                $errors[] = "Failed to add tag: {$tag}";
            }
        }

        if ($errors) {
            return SyncResult::failure(implode(', ', $errors));
        }

        return SyncResult::success('Tags added successfully');
    }

    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $subscriberId = $this->getSubscriberId($email);

        if (!$subscriberId) {
            return SyncResult::failure('Subscriber not found');
        }

        $errors = [];

        foreach ($tags as $tag) {
            $tagId = $this->getTagId($tag);

            if (!$tagId) {
                continue;
            }

            $response = $this->makeRequest(
                'delete',
                "{$this->baseUrl}/subscribers/{$subscriberId}/tags/{$tagId}",
                ['api_secret' => $this->config['api_secret']]
            );

            if (!$response['success'] && $response['status'] !== 404) {
                $errors[] = "Failed to remove tag: {$tag}";
            }
        }

        if ($errors) {
            return SyncResult::failure(implode(', ', $errors));
        }

        return SyncResult::success('Tags removed successfully');
    }

    public function getName(): string
    {
        return 'convertkit';
    }

    protected function getDefaultListId(): ?string
    {
        return $this->config['default_form_id'] ?? null;
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getSubscriberByEmail(string $email): ?array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/subscribers", [
            'api_secret' => $this->config['api_secret'],
            'email_address' => $email,
        ]);

        if (!$response['success'] || empty($response['body']['subscribers'])) {
            return null;
        }

        return $response['body']['subscribers'][0];
    }

    private function getSubscriberId(string $email): ?string
    {
        $subscriber = $this->getSubscriberByEmail($email);

        return $subscriber ? (string) $subscriber['id'] : null;
    }

    private function getTagId(string $tagName): ?string
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/tags", [
            'api_key' => $this->config['api_key'],
        ]);

        if (!$response['success']) {
            return null;
        }

        foreach ($response['body']['tags'] ?? [] as $tag) {
            if (strcasecmp($tag['name'], $tagName) === 0) {
                return (string) $tag['id'];
            }
        }

        return null;
    }

    private function getOrCreateTag(string $tagName): ?string
    {
        $tagId = $this->getTagId($tagName);

        if ($tagId) {
            return $tagId;
        }

        $response = $this->makeRequest('post', "{$this->baseUrl}/tags", [
            'api_key' => $this->config['api_key'],
            'tag' => ['name' => $tagName],
        ]);

        if ($response['success']) {
            return (string) $response['body']['id'];
        }

        return null;
    }
}
