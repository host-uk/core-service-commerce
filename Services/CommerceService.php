<?php

namespace Core\Mod\Commerce\Services;

use Core\Mod\Tenant\Models\Package;
use Core\Mod\Tenant\Models\Workspace;
use Core\Mod\Tenant\Services\EntitlementService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Core\Mod\Commerce\Contracts\Orderable;
use Core\Mod\Commerce\Models\Coupon;
use Core\Mod\Commerce\Models\Invoice;
use Core\Mod\Commerce\Models\Order;
use Core\Mod\Commerce\Models\OrderItem;
use Core\Mod\Commerce\Models\Payment;
use Core\Mod\Commerce\Models\Subscription;
use Core\Mod\Commerce\Services\PaymentGateway\PaymentGatewayContract;

/**
 * Main commerce orchestration service.
 *
 * Handles order creation, checkout flow, and payment processing.
 */
class CommerceService
{
    public function __construct(
        protected EntitlementService $entitlements,
        protected TaxService $taxService,
        protected CouponService $couponService,
        protected InvoiceService $invoiceService,
        protected CurrencyService $currencyService,
    ) {}

    /**
     * Get the active payment gateway.
     */
    public function gateway(?string $name = null): PaymentGatewayContract
    {
        $name = $name ?? $this->getDefaultGateway();

        return app("commerce.gateway.{$name}");
    }

    /**
     * Get the default gateway name.
     */
    public function getDefaultGateway(): string
    {
        // BTCPay is primary, Stripe is fallback
        if (config('commerce.gateways.btcpay.enabled')) {
            return 'btcpay';
        }

        return 'stripe';
    }

    /**
     * Get all enabled gateways.
     *
     * @return array<string, PaymentGatewayContract>
     */
    public function getEnabledGateways(): array
    {
        $gateways = [];

        foreach (config('commerce.gateways') as $name => $config) {
            if ($config['enabled'] ?? false) {
                $gateways[$name] = $this->gateway($name);
            }
        }

        return $gateways;
    }

    // Order Creation

    /**
     * Create an order for a package purchase.
     *
     * @param  string|null  $idempotencyKey  Optional idempotency key to prevent duplicate orders
     */
    public function createOrder(
        Orderable&Model $orderable,
        Package $package,
        string $billingCycle = 'monthly',
        ?Coupon $coupon = null,
        array $metadata = [],
        ?string $idempotencyKey = null
    ): Order {
        // Check for existing order with same idempotency key
        if ($idempotencyKey) {
            $existingOrder = Order::where('idempotency_key', $idempotencyKey)->first();
            if ($existingOrder) {
                return $existingOrder;
            }
        }

        return DB::transaction(function () use ($orderable, $package, $billingCycle, $coupon, $metadata, $idempotencyKey) {
            // Calculate pricing
            $subtotal = $package->getPrice($billingCycle);
            $setupFee = $package->setup_fee ?? 0;

            // Apply coupon if valid
            $discountAmount = 0;
            if ($coupon && $this->couponService->validateForOrderable($coupon, $orderable, $package)) {
                $discountAmount = $coupon->calculateDiscount($subtotal);
            }

            // Calculate tax
            $taxableAmount = $subtotal - $discountAmount + $setupFee;
            $taxResult = $this->taxService->calculateForOrderable($orderable, $taxableAmount);

            // Create order
            $order = Order::create([
                'orderable_type' => get_class($orderable),
                'orderable_id' => $orderable->id,
                'user_id' => $orderable instanceof \Core\Mod\Tenant\Models\User ? $orderable->id : null,
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending',
                'billing_cycle' => $billingCycle,
                'subtotal' => $subtotal + $setupFee,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxResult->taxAmount,
                'tax_rate' => $taxResult->taxRate,
                'tax_country' => $taxResult->jurisdiction,
                'total' => $subtotal - $discountAmount + $setupFee + $taxResult->taxAmount,
                'currency' => config('commerce.currency', 'GBP'),
                'coupon_id' => $coupon?->id,
                'billing_name' => $orderable->getBillingName(),
                'billing_email' => $orderable->getBillingEmail(),
                'billing_address' => $orderable->getBillingAddress(),
                'metadata' => $metadata,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Create line items
            $lineTotal = $subtotal - $discountAmount;
            OrderItem::create([
                'order_id' => $order->id,
                'item_type' => 'package',
                'item_id' => $package->id,
                'item_code' => $package->code,
                'description' => "{$package->name} - ".ucfirst($billingCycle),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $lineTotal,
                'billing_cycle' => $billingCycle,
            ]);

            // Add setup fee as separate line item if applicable
            if ($setupFee > 0) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'item_type' => 'setup_fee',
                    'item_id' => $package->id,
                    'item_code' => 'setup-fee',
                    'description' => "One-time setup fee for {$package->name}",
                    'quantity' => 1,
                    'unit_price' => $setupFee,
                    'line_total' => $setupFee,
                    'billing_cycle' => 'onetime',
                ]);
            }

            return $order;
        });
    }

    /**
     * Create a checkout session for an order.
     *
     * @return array{order: Order, session_id: string, checkout_url: string}
     */
    public function createCheckout(
        Order $order,
        ?string $gateway = null,
        ?string $successUrl = null,
        ?string $cancelUrl = null
    ): array {
        $gateway = $gateway ?? $this->getDefaultGateway();
        $successUrl = $successUrl ?? route('checkout.success', ['order' => $order->order_number]);
        $cancelUrl = $cancelUrl ?? route('checkout.cancel', ['order' => $order->order_number]);

        // Ensure customer exists in gateway (only for Workspace orderables)
        if ($order->orderable instanceof Workspace) {
            $this->ensureCustomer($order->orderable, $gateway);
        }

        // Update order with gateway info
        $order->update([
            'gateway' => $gateway,
            'status' => 'processing',
        ]);

        // Create checkout session
        $session = $this->gateway($gateway)->createCheckoutSession($order, $successUrl, $cancelUrl);

        $order->update([
            'gateway_session_id' => $session['session_id'],
        ]);

        return [
            'order' => $order->fresh(),
            'session_id' => $session['session_id'],
            'checkout_url' => $session['checkout_url'],
        ];
    }

    /**
     * Ensure workspace has a customer ID in the gateway.
     */
    public function ensureCustomer(Workspace $workspace, string $gateway): string
    {
        $field = "{$gateway}_customer_id";

        if ($workspace->{$field}) {
            return $workspace->{$field};
        }

        $customerId = $this->gateway($gateway)->createCustomer($workspace);

        $workspace->update([$field => $customerId]);

        return $customerId;
    }

    /**
     * Create an order for a one-time boost purchase.
     */
    public function createBoostOrder(
        Orderable&Model $orderable,
        string $boostCode,
        string $boostName,
        int $price,
        ?Coupon $coupon = null,
        array $metadata = []
    ): Order {
        return DB::transaction(function () use ($orderable, $boostCode, $boostName, $price, $coupon, $metadata) {
            // Calculate pricing
            $subtotal = $price;

            // Apply coupon if valid
            $discountAmount = 0;
            if ($coupon) {
                $discountAmount = $coupon->calculateDiscount($subtotal);
            }

            // Calculate tax
            $taxableAmount = $subtotal - $discountAmount;
            $taxResult = $this->taxService->calculateForOrderable($orderable, $taxableAmount);

            // Create order
            $order = Order::create([
                'orderable_type' => get_class($orderable),
                'orderable_id' => $orderable->id,
                'user_id' => $orderable instanceof \Core\Mod\Tenant\Models\User ? $orderable->id : null,
                'order_number' => Order::generateOrderNumber(),
                'status' => 'pending',
                'billing_cycle' => 'onetime',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxResult->taxAmount,
                'tax_rate' => $taxResult->taxRate,
                'tax_country' => $taxResult->jurisdiction,
                'total' => $subtotal - $discountAmount + $taxResult->taxAmount,
                'currency' => config('commerce.currency', 'GBP'),
                'coupon_id' => $coupon?->id,
                'billing_name' => $orderable->getBillingName(),
                'billing_email' => $orderable->getBillingEmail(),
                'billing_address' => $orderable->getBillingAddress(),
                'metadata' => array_merge($metadata, ['boost_code' => $boostCode]),
            ]);

            // Create line item
            OrderItem::create([
                'order_id' => $order->id,
                'item_type' => 'boost',
                'item_id' => null,
                'item_code' => $boostCode,
                'description' => $boostName,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'line_total' => $subtotal - $discountAmount,
                'billing_cycle' => 'onetime',
            ]);

            return $order;
        });
    }

    // Order Fulfilment

    /**
     * Process a successful payment and provision entitlements.
     */
    public function fulfillOrder(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            // Mark order as paid
            $order->markAsPaid();

            // Create invoice
            $invoice = $this->invoiceService->createFromOrder($order, $payment);

            // Record coupon usage if applicable
            if ($order->coupon_id && $order->orderable) {
                $this->couponService->recordUsageForOrderable(
                    $order->coupon,
                    $order->orderable,
                    $order,
                    $order->discount_amount
                );
            }

            // Provision entitlements for each package item (only for Workspace orderables)
            if ($order->orderable instanceof Workspace) {
                foreach ($order->items as $item) {
                    if ($item->item_type === 'package' && $item->item_id) {
                        $this->entitlements->provisionPackage(
                            $order->orderable,
                            $item->package->code,
                            [
                                'order_id' => $order->id,
                                'source' => $order->gateway,
                            ]
                        );
                    }
                }
            }

            // Provision boosts for user-level orders
            if ($order->orderable instanceof \Core\Mod\Tenant\Models\User) {
                foreach ($order->items as $item) {
                    if ($item->item_type === 'boost') {
                        $quantity = $item->metadata['quantity'] ?? $item->quantity ?? 1;
                        $this->provisionBoostForUser($order->orderable, $item->item_code, $quantity, [
                            'order_id' => $order->id,
                            'source' => $order->gateway,
                        ]);
                    }
                }
            }

            // Dispatch OrderPaid event for referral tracking and other listeners
            event(new \Core\Mod\Commerce\Events\OrderPaid($order, $payment));
        });
    }

    /**
     * Provision a boost for a user.
     */
    public function provisionBoostForUser(\Core\Mod\Tenant\Models\User $user, string $featureCode, int $quantity = 1, array $metadata = []): \Core\Mod\Tenant\Models\Boost
    {
        // Use ADD_LIMIT for quantity-based boosts, ENABLE for boolean boosts
        $boostType = $quantity > 1 || $this->isQuantityBasedFeature($featureCode)
            ? \Core\Mod\Tenant\Models\Boost::BOOST_TYPE_ADD_LIMIT
            : \Core\Mod\Tenant\Models\Boost::BOOST_TYPE_ENABLE;

        return \Core\Mod\Tenant\Models\Boost::create([
            'user_id' => $user->id,
            'workspace_id' => null,
            'feature_code' => $featureCode,
            'boost_type' => $boostType,
            'duration_type' => \Core\Mod\Tenant\Models\Boost::DURATION_PERMANENT,
            'limit_value' => $boostType === \Core\Mod\Tenant\Models\Boost::BOOST_TYPE_ADD_LIMIT ? $quantity : null,
            'status' => \Core\Mod\Tenant\Models\Boost::STATUS_ACTIVE,
            'starts_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Check if a feature code is quantity-based (needs ADD_LIMIT).
     */
    protected function isQuantityBasedFeature(string $featureCode): bool
    {
        return in_array($featureCode, [
            'bio.pages',
            'bio.blocks',
            'bio.shortened_links',
            'bio.qr_codes',
            'bio.file_downloads',
            'bio.events',
            'bio.vcard',
            'bio.splash_pages',
            'bio.pixels',
            'bio.static_sites',
            'bio.custom_domains',
            'bio.web3_domains',
            'ai.credits',
            'webpage.sub_pages',
        ]);
    }

    /**
     * Handle a failed order.
     */
    public function failOrder(Order $order, ?string $reason = null): void
    {
        $order->markAsFailed($reason);
    }

    // Subscription Management

    /**
     * Create a subscription for a workspace.
     */
    public function createSubscription(
        Workspace $workspace,
        Package $package,
        string $billingCycle = 'monthly',
        ?string $gateway = null
    ): Subscription {
        $gateway = $gateway ?? $this->getDefaultGateway();
        $priceId = $package->getGatewayPriceId($gateway, $billingCycle);

        if (! $priceId) {
            throw new \InvalidArgumentException(
                "Package {$package->code} has no {$gateway} price ID for {$billingCycle} billing"
            );
        }

        // Ensure customer exists
        $this->ensureCustomer($workspace, $gateway);

        // Create subscription in gateway
        $subscription = $this->gateway($gateway)->createSubscription($workspace, $priceId, [
            'trial_days' => $package->trial_days,
        ]);

        return $subscription;
    }

    /**
     * Upgrade or downgrade a subscription.
     */
    public function changeSubscription(
        Subscription $subscription,
        Package $newPackage,
        ?string $billingCycle = null
    ): Subscription {
        $billingCycle = $billingCycle ?? $this->guessBillingCycleFromSubscription($subscription);
        $priceId = $newPackage->getGatewayPriceId($subscription->gateway, $billingCycle);

        if (! $priceId) {
            throw new \InvalidArgumentException(
                "Package {$newPackage->code} has no {$subscription->gateway} price ID"
            );
        }

        return $this->gateway($subscription->gateway)->updateSubscription($subscription, [
            'price_id' => $priceId,
            'prorate' => config('commerce.subscriptions.allow_proration', true),
        ]);
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, bool $immediately = false): void
    {
        $this->gateway($subscription->gateway)->cancelSubscription($subscription, $immediately);

        if ($immediately) {
            // Revoke entitlements immediately
            $workspacePackage = $subscription->workspacePackage;
            if ($workspacePackage) {
                $this->entitlements->revokePackage($subscription->workspace, $workspacePackage->package->code);
            }
        }
    }

    /**
     * Resume a cancelled subscription.
     */
    public function resumeSubscription(Subscription $subscription): void
    {
        if (! $subscription->onGracePeriod()) {
            throw new \InvalidArgumentException('Cannot resume subscription outside grace period');
        }

        $this->gateway($subscription->gateway)->resumeSubscription($subscription);
    }

    // Refunds

    /**
     * Process a refund.
     */
    public function refund(
        Payment $payment,
        ?float $amount = null,
        ?string $reason = null
    ): \Core\Mod\Commerce\Models\Refund {
        $amountCents = $amount
            ? (int) ($amount * 100)
            : (int) (($payment->amount - $payment->amount_refunded) * 100);

        return $this->gateway($payment->gateway)->refund($payment, $amountCents, $reason);
    }

    // Invoice Retries

    /**
     * Retry payment for an invoice.
     */
    public function retryInvoicePayment(Invoice $invoice): bool
    {
        if ($invoice->isPaid()) {
            return true; // Already paid
        }

        $workspace = $invoice->workspace;
        if (! $workspace) {
            return false;
        }

        // Get default payment method
        $paymentMethod = $workspace->paymentMethods()
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $paymentMethod) {
            return false;
        }

        try {
            $gateway = $this->gateway($paymentMethod->gateway);

            // Convert total to cents and charge via gateway
            $amountCents = (int) ($invoice->total * 100);
            $payment = $gateway->chargePaymentMethod(
                $paymentMethod,
                $amountCents,
                $invoice->currency,
                [
                    'description' => "Invoice {$invoice->invoice_number}",
                    'invoice_id' => $invoice->id,
                ]
            );

            // Gateway returns a Payment model - check if it succeeded
            if ($payment->status === 'succeeded') {
                // Link payment to invoice
                $payment->update(['invoice_id' => $invoice->id]);
                $invoice->markAsPaid($payment);

                return true;
            }

            // For BTCPay, payment will be 'pending' as it requires customer action
            // This is expected - automatic retry won't work for crypto payments
            if ($payment->status === 'pending' && $paymentMethod->gateway === 'btcpay') {
                \Illuminate\Support\Facades\Log::info('BTCPay invoice created for retry - requires customer payment', [
                    'invoice_id' => $invoice->id,
                    'payment_id' => $payment->id,
                ]);
            }

            return false;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Invoice payment retry failed', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get gateway by name (alias for gateway method).
     */
    public function getGateway(string $name): PaymentGatewayContract
    {
        return $this->gateway($name);
    }

    // Helpers

    /**
     * Guess billing cycle from subscription metadata.
     */
    protected function guessBillingCycleFromSubscription(Subscription $subscription): string
    {
        // Try to determine from current period length
        $periodDays = $subscription->current_period_start->diffInDays($subscription->current_period_end);

        return $periodDays > 32 ? 'yearly' : 'monthly';
    }

    /**
     * Get currency symbol.
     */
    public function getCurrencySymbol(?string $currency = null): string
    {
        $currency = $currency ?? config('commerce.currency', 'GBP');

        return $this->currencyService->getSymbol($currency);
    }

    /**
     * Format money for display.
     */
    public function formatMoney(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('commerce.currency', 'GBP');

        return $this->currencyService->format($amount, $currency);
    }

    /**
     * Get the currency service.
     */
    public function getCurrencyService(): CurrencyService
    {
        return $this->currencyService;
    }

    /**
     * Convert an amount between currencies.
     */
    public function convertCurrency(float $amount, string $from, string $to): ?float
    {
        return $this->currencyService->convert($amount, $from, $to);
    }
}
