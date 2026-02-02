<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Contracts;

use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;

interface SubscribeProviderContract
{
    /**
     * Subscribe an email to a list.
     */
    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult;

    /**
     * Unsubscribe an email from a list.
     */
    public function unsubscribe(string $email, ?string $listId = null): SyncResult;

    /**
     * Update subscriber information.
     */
    public function update(Subscriber $subscriber, ?string $listId = null): SyncResult;

    /**
     * Check if an email is subscribed.
     */
    public function isSubscribed(string $email, ?string $listId = null): bool;

    /**
     * Get subscriber information.
     */
    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber;

    /**
     * Get all lists/audiences from the provider.
     */
    public function getLists(): array;

    /**
     * Create a new list/audience.
     */
    public function createList(SubscriberList $list): SyncResult;

    /**
     * Add tags to a subscriber.
     */
    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult;

    /**
     * Remove tags from a subscriber.
     */
    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
