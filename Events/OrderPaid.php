<?php

declare(strict_types=1);

namespace Core\Commerce\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Core\Commerce\Models\Order;
use Core\Commerce\Models\Payment;

/**
 * Event fired when an order is successfully paid.
 */
class OrderPaid
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Order $order,
        public Payment $payment
    ) {}
}
