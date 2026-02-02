<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Nejcc\Subscribe\Database\Factories\SubscriptionListFactory;

final class SubscriptionList extends Model
{
    use HasFactory;

    protected static function newFactory(): SubscriptionListFactory
    {
        return SubscriptionListFactory::new();
    }

    protected $table = 'subscription_lists';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_public',
        'is_default',
        'double_opt_in',
        'welcome_email_enabled',
        'welcome_email_subject',
        'welcome_email_content',
        'confirmation_email_subject',
        'confirmation_email_content',
        'provider',
        'provider_id',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_default' => 'boolean',
            'double_opt_in' => 'boolean',
            'welcome_email_enabled' => 'boolean',
        ];
    }

    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(
            Subscriber::class,
            'subscriber_list',
            'list_id',
            'subscriber_id'
        )->withTimestamps();
    }

    public function activeSubscribers(): BelongsToMany
    {
        return $this->subscribers()->where('status', 'subscribed');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublic(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_public', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeDefault(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_default', true);
    }

    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->first();
    }

    public function requiresDoubleOptIn(): bool
    {
        return $this->double_opt_in;
    }

    public function hasWelcomeEmail(): bool
    {
        return $this->welcome_email_enabled && $this->welcome_email_subject && $this->welcome_email_content;
    }
}
