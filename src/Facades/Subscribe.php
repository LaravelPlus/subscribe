<?php

declare(strict_types=1);

namespace LaravelPlus\Subscribe\Facades;

use Illuminate\Support\Facades\Facade;
use LaravelPlus\Subscribe\Contracts\SubscribeProviderContract;
use LaravelPlus\Subscribe\DTOs\Subscriber;
use LaravelPlus\Subscribe\DTOs\SubscriberList;
use LaravelPlus\Subscribe\DTOs\SyncResult;
use LaravelPlus\Subscribe\SubscribeManager;

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
