<?php

namespace Core\Commerce\Events;

use Core\Commerce\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?string $previousStatus = null
    ) {}
}
