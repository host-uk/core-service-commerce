<?php

namespace Core\Commerce\Listeners;

use Core\Commerce\Events\SubscriptionRenewed;
use Core\Commerce\Services\UsageBillingService;

/**
 * Reset usage records when a subscription renews.
 *
 * Creates fresh usage records for the new billing period.
 */
class ResetUsageOnRenewal
{
    public function __construct(
        protected UsageBillingService $usageBilling
    ) {}

    public function handle(SubscriptionRenewed $event): void
    {
        if (! config('commerce.features.usage_billing', false)) {
            return;
        }

        $this->usageBilling->onPeriodReset($event->subscription);
    }
}
