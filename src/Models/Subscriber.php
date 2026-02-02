<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nejcc\Subscribe\Database\Factories\SubscriberFactory;

final class Subscriber extends Model
{
    use HasFactory;

    protected static function newFactory(): SubscriberFactory
    {
        return SubscriberFactory::new();
    }

    protected $table = 'subscribers';

    protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'phone',
        'company',
        'attributes',
        'tags',
        'source',
        'ip_address',
        'status',
        'confirmed_at',
        'confirmation_token',
        'provider',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'tags' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(
            SubscriptionList::class,
            'subscriber_list',
            'subscriber_id',
            'list_id'
        )->withTimestamps();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeSubscribed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'subscribed');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUnsubscribed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'unsubscribed');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeConfirmed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeUnconfirmed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereNull('confirmed_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeInList(\Illuminate\Database\Eloquent\Builder $query, string $listId): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereHas('lists', fn ($q) => $q->where('subscription_lists.id', $listId));
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeWithTag(\Illuminate\Database\Eloquent\Builder $query, string $tag): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereJsonContains('tags', $tag);
    }

    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function confirm(): void
    {
        $this->update([
            'status' => 'subscribed',
            'confirmed_at' => now(),
            'confirmation_token' => null,
        ]);
    }

    public function unsubscribe(): void
    {
        $this->update(['status' => 'unsubscribed']);
    }

    public function addTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    public function removeTag(string $tag): void
    {
        $tags = $this->tags ?? [];
        $this->update(['tags' => array_values(array_diff($tags, [$tag]))]);
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? []);
    }

    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);

        return $parts ? implode(' ', $parts) : null;
    }
}
