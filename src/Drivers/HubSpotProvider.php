<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Drivers;

use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\DTOs\SubscriberList;
use LaravelPlus\Subscribe\DTOs\SyncResult;

final class HubSpotProvider extends AbstractProvider
{
    protected string $baseUrl = 'https://api.hubapi.com';

    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $properties = array_filter([
            'email' => $subscriber->email,
            'firstname' => $subscriber->firstName,
            'lastname' => $subscriber->lastName,
            'phone' => $subscriber->phone,
            'company' => $subscriber->company,
            ...$subscriber->attributes,
        ]);

        $payload = [
            'properties' => $properties,
        ];

        $response = $this->makeRequest('post', "{$this->baseUrl}/crm/v3/objects/contacts", $payload);

        if ($response['success']) {
            $contactId = $response['body']['id'];

            if ($listId) {
                $this->addContactToList($contactId, $listId);
            }

            return SyncResult::success(
                message: 'Subscriber added successfully',
                providerId: $contactId,
            );
        }

        if ($response['status'] === 409) {
            $existingId = $this->getContactIdByEmail($subscriber->email);
            if ($existingId) {
                $this->updateContactProperties($existingId, $properties);

                if ($listId) {
                    $this->addContactToList($existingId, $listId);
                }

                return SyncResult::success(
                    message: 'Subscriber updated successfully',
                    providerId: $existingId,
                );
            }
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to add subscriber',
            errorCode: (string) $response['status'],
            data: $response['body'],
        );
    }

    public function unsubscribe(string $email, ?string $listId = null): SyncResult
    {
        $contactId = $this->getContactIdByEmail($email);

        if (!$contactId) {
            return SyncResult::failure('Contact not found');
        }

        if ($listId) {
            $response = $this->makeRequest(
                'delete',
                "{$this->baseUrl}/contacts/v1/lists/{$listId}/remove",
                ['vids' => [(int) $contactId]]
            );
        } else {
            $response = $this->makeRequest(
                'patch',
                "{$this->baseUrl}/crm/v3/objects/contacts/{$contactId}",
                ['properties' => ['hs_email_optout' => 'true']]
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
        $contactId = $this->getContactIdByEmail($subscriber->email);

        if (!$contactId) {
            return SyncResult::failure('Contact not found');
        }

        $properties = array_filter([
            'firstname' => $subscriber->firstName,
            'lastname' => $subscriber->lastName,
            'phone' => $subscriber->phone,
            'company' => $subscriber->company,
            ...$subscriber->attributes,
        ]);

        $this->updateContactProperties($contactId, $properties);

        return SyncResult::success('Subscriber updated successfully');
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $contact = $this->getContactByEmail($email);

        if (!$contact) {
            return false;
        }

        $optedOut = $contact['properties']['hs_email_optout'] ?? 'false';

        if ($optedOut === 'true') {
            return false;
        }

        if ($listId) {
            return $this->isContactInList($contact['id'], $listId);
        }

        return true;
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $contact = $this->getContactByEmail($email);

        if (!$contact) {
            return null;
        }

        $properties = $contact['properties'] ?? [];

        return Subscriber::fromArray([
            'email' => $properties['email'] ?? $email,
            'first_name' => $properties['firstname'] ?? null,
            'last_name' => $properties['lastname'] ?? null,
            'phone' => $properties['phone'] ?? null,
            'company' => $properties['company'] ?? null,
            'attributes' => $properties,
            'status' => ($properties['hs_email_optout'] ?? 'false') === 'true' ? 'unsubscribed' : 'subscribed',
            'provider_id' => $contact['id'],
        ]);
    }

    public function getLists(): array
    {
        $response = $this->makeRequest('get', "{$this->baseUrl}/contacts/v1/lists", ['count' => 100]);

        if (!$response['success']) {
            return [];
        }

        return array_map(
            fn ($list) => SubscriberList::fromArray([
                'id' => (string) $list['listId'],
                'name' => $list['name'],
                'subscriber_count' => $list['metaData']['size'] ?? 0,
                'provider_id' => (string) $list['listId'],
            ]),
            $response['body']['lists'] ?? []
        );
    }

    public function createList(SubscriberList $list): SyncResult
    {
        $payload = [
            'name' => $list->name,
            'dynamic' => false,
        ];

        $response = $this->makeRequest('post', "{$this->baseUrl}/contacts/v1/lists", $payload);

        if ($response['success']) {
            return SyncResult::success(
                message: 'List created successfully',
                providerId: (string) $response['body']['listId'],
            );
        }

        return SyncResult::failure(
            message: $response['body']['message'] ?? 'Failed to create list',
            errorCode: (string) $response['status'],
        );
    }

    public function getName(): string
    {
        return 'hubspot';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function getContactIdByEmail(string $email): ?string
    {
        $contact = $this->getContactByEmail($email);

        return $contact ? $contact['id'] : null;
    }

    private function getContactByEmail(string $email): ?array
    {
        $response = $this->makeRequest(
            'post',
            "{$this->baseUrl}/crm/v3/objects/contacts/search",
            [
                'filterGroups' => [
                    [
                        'filters' => [
                            [
                                'propertyName' => 'email',
                                'operator' => 'EQ',
                                'value' => $email,
                            ],
                        ],
                    ],
                ],
                'properties' => ['email', 'firstname', 'lastname', 'phone', 'company', 'hs_email_optout'],
            ]
        );

        if (!$response['success'] || empty($response['body']['results'])) {
            return null;
        }

        return $response['body']['results'][0];
    }

    private function updateContactProperties(string $contactId, array $properties): void
    {
        $this->makeRequest(
            'patch',
            "{$this->baseUrl}/crm/v3/objects/contacts/{$contactId}",
            ['properties' => $properties]
        );
    }

    private function addContactToList(string $contactId, string $listId): void
    {
        $this->makeRequest(
            'post',
            "{$this->baseUrl}/contacts/v1/lists/{$listId}/add",
            ['vids' => [(int) $contactId]]
        );
    }

    private function isContactInList(string $contactId, string $listId): bool
    {
        $response = $this->makeRequest(
            'get',
            "{$this->baseUrl}/contacts/v1/lists/{$listId}/contacts/all",
            ['count' => 100]
        );

        if (!$response['success']) {
            return false;
        }

        $contacts = $response['body']['contacts'] ?? [];

        foreach ($contacts as $contact) {
            if ((string) $contact['vid'] === $contactId) {
                return true;
            }
        }

        return false;
    }
}
