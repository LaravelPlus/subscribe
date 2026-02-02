<?php

declare(strict_types=1);

namespace Nejcc\Subscribe\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Nejcc\Subscribe\Models\Subscriber;

final class SubscriberUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Subscriber $subscriber,
    ) {}
}
