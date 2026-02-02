<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Drivers;

use Illuminate\Support\Facades\Http;
use Nejcc\Subscribe\Contracts\SubscribeProviderContract;
use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;

abstract class AbstractProvider implements SubscribeProviderContract
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    abstract public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult;

    abstract public function unsubscribe(string $email, ?string $listId = null): SyncResult;

    abstract public function update(Subscriber $subscriber, ?string $listId = null): SyncResult;

    abstract public function isSubscribed(string $email, ?string $listId = null): bool;

    abstract public function getSubscriber(string $email, ?string $listId = null): ?Subscriber;

    abstract public function getLists(): array;

    abstract public function createList(SubscriberList $list): SyncResult;

    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        return SyncResult::failure('Tags not supported by this provider');
    }

    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        return SyncResult::failure('Tags not supported by this provider');
    }

    abstract public function getName(): string;

    protected function getDefaultListId(): ?string
    {
        return $this->config['default_list_id'] ?? null;
    }

    protected function makeRequest(string $method, string $url, array $options = []): array
    {
        $response = Http::withHeaders($this->getHeaders())
            ->{$method}($url, $options);

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->json() ?? [],
        ];
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
