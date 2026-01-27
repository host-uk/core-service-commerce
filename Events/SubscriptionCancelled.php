<?php

namespace Core\Commerce\Events;

use Core\Commerce\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public bool $immediate = false
    ) {}
}
