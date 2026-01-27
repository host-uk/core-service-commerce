<?php

namespace Core\Commerce\Events;

use Core\Commerce\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public ?\DateTimeInterface $previousPeriodEnd = null
    ) {}
}
