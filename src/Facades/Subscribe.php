<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Facades;

use Illuminate\Support\Facades\Facade;
use Nejcc\Subscribe\Contracts\SubscribeProviderContract;
use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;
use Nejcc\Subscribe\SubscribeManager;

/**
 * @method static SyncResult subscribe(Subscriber $subscriber, ?string $listId = null)
 * @method static SyncResult unsubscribe(string $email, ?string $listId = null)
 * @method static SyncResult update(Subscriber $subscriber, ?string $listId = null)
 * @method static bool isSubscribed(string $email, ?string $listId = null)
 * @method static Subscriber|null getSubscriber(string $email, ?string $listId = null)
 * @method static array getLists()
 * @method static SyncResult createList(SubscriberList $list)
 * @method static SyncResult addTags(string $email, array $tags, ?string $listId = null)
 * @method static SyncResult removeTags(string $email, array $tags, ?string $listId = null)
 * @method static string getName()
 * @method static SubscribeProviderContract provider(?string $name = null)
 * @method static SubscribeProviderContract driver(?string $driver = null)
 *
 * @see SubscribeManager
 */
final class Subscribe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SubscribeManager::class;
    }
}
