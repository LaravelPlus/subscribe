<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Drivers;

use Nejcc\Subscribe\DTOs\Subscriber;
use Nejcc\Subscribe\DTOs\SubscriberList;
use Nejcc\Subscribe\DTOs\SyncResult;
use Nejcc\Subscribe\Models\Subscriber as SubscriberModel;
use Nejcc\Subscribe\Models\SubscriptionList;

final class DatabaseProvider extends AbstractProvider
{
    public function subscribe(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $model = SubscriberModel::updateOrCreate(
            ['email' => $subscriber->email],
            [
                'first_name' => $subscriber->firstName,
                'last_name' => $subscriber->lastName,
                'phone' => $subscriber->phone,
                'company' => $subscriber->company,
                'attributes' => $subscriber->attributes,
                'tags' => $subscriber->tags,
                'source' => $subscriber->source,
                'ip_address' => $subscriber->ipAddress,
                'status' => $subscriber->status,
            ]
        );

        if ($listId) {
            $list = SubscriptionList::find($listId);
            if ($list) {
                $model->lists()->syncWithoutDetaching([$list->id]);
            }
        }

        return SyncResult::success(
            message: 'Subscriber added successfully',
            providerId: (string) $model->id,
            data: ['subscriber_id' => $model->id]
        );
    }

    public function unsubscribe(string $email, ?string $listId = null): SyncResult
    {
        $subscriber = SubscriberModel::where('email', $email)->first();

        if (!$subscriber) {
            return SyncResult::failure('Subscriber not found');
        }

        if ($listId) {
            $subscriber->lists()->detach($listId);
        } else {
            $subscriber->update(['status' => 'unsubscribed']);
        }

        return SyncResult::success('Unsubscribed successfully');
    }

    public function update(Subscriber $subscriber, ?string $listId = null): SyncResult
    {
        $model = SubscriberModel::where('email', $subscriber->email)->first();

        if (!$model) {
            return SyncResult::failure('Subscriber not found');
        }

        $model->update([
            'first_name' => $subscriber->firstName,
            'last_name' => $subscriber->lastName,
            'phone' => $subscriber->phone,
            'company' => $subscriber->company,
            'attributes' => $subscriber->attributes,
            'tags' => $subscriber->tags,
        ]);

        return SyncResult::success('Subscriber updated successfully');
    }

    public function isSubscribed(string $email, ?string $listId = null): bool
    {
        $query = SubscriberModel::where('email', $email)
            ->where('status', 'subscribed');

        if ($listId) {
            $query->whereHas('lists', fn ($q) => $q->where('subscription_lists.id', $listId));
        }

        return $query->exists();
    }

    public function getSubscriber(string $email, ?string $listId = null): ?Subscriber
    {
        $model = SubscriberModel::where('email', $email)->first();

        if (!$model) {
            return null;
        }

        return Subscriber::fromArray([
            'email' => $model->email,
            'first_name' => $model->first_name,
            'last_name' => $model->last_name,
            'phone' => $model->phone,
            'company' => $model->company,
            'attributes' => $model->attributes ?? [],
            'tags' => $model->tags ?? [],
            'lists' => $model->lists->pluck('id')->toArray(),
            'source' => $model->source,
            'ip_address' => $model->ip_address,
            'status' => $model->status,
            'provider_id' => (string) $model->id,
        ]);
    }

    public function getLists(): array
    {
        return SubscriptionList::withCount('subscribers')
            ->get()
            ->map(fn ($list) => SubscriberList::fromArray([
                'id' => (string) $list->id,
                'name' => $list->name,
                'description' => $list->description,
                'subscriber_count' => $list->subscribers_count,
                'is_public' => $list->is_public,
                'double_opt_in' => $list->double_opt_in,
            ]))
            ->toArray();
    }

    public function createList(SubscriberList $list): SyncResult
    {
        $model = SubscriptionList::create([
            'name' => $list->name,
            'description' => $list->description,
            'is_public' => $list->isPublic,
            'double_opt_in' => $list->doubleOptIn,
        ]);

        return SyncResult::success(
            message: 'List created successfully',
            providerId: (string) $model->id
        );
    }

    public function addTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $subscriber = SubscriberModel::where('email', $email)->first();

        if (!$subscriber) {
            return SyncResult::failure('Subscriber not found');
        }

        $currentTags = $subscriber->tags ?? [];
        $subscriber->update(['tags' => array_unique(array_merge($currentTags, $tags))]);

        return SyncResult::success('Tags added successfully');
    }

    public function removeTags(string $email, array $tags, ?string $listId = null): SyncResult
    {
        $subscriber = SubscriberModel::where('email', $email)->first();

        if (!$subscriber) {
            return SyncResult::failure('Subscriber not found');
        }

        $currentTags = $subscriber->tags ?? [];
        $subscriber->update(['tags' => array_diff($currentTags, $tags)]);

        return SyncResult::success('Tags removed successfully');
    }

    public function getName(): string
    {
        return 'database';
    }
}
