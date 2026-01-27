# Native Commerce Plan: Replacing Blesta with Laravel

## Executive Summary

This document outlines the plan to replace the external Blesta billing system (order.host.uk.com) with native Laravel commerce built directly into Host Hub. The goal is to eliminate external dependencies, reduce complexity, and enable tighter integration with the existing entitlement system.

**Key Finding**: The current entitlement system is **already decoupled** from payment processing. Whether Blesta or Stripe provisions a package, the same `EntitlementService->provisionPackage()` call is used. This means native commerce can be added without refactoring the entitlement model itself.

---

## Current Architecture

### What Blesta Does Today
| Function | Blesta | Host Hub |
|----------|--------|----------|
| Product Catalog | `packages` table (94 tables total) | `entitlement_packages` ✓ |
| Subscriptions | `services` table | `entitlement_workspace_packages` ✓ |
| Feature Limits | Custom via module | `entitlement_features` ✓ |
| Usage Tracking | N/A | `entitlement_usage_records` ✓ |
| Payment Processing | 20+ gateways | **MISSING** |
| Invoices | Full engine | **MISSING** |
| Checkout Flow | Full cart/checkout | **MISSING** |
| Customer Portal | Self-service billing | **MISSING** |

### Integration Flow Today
```
Host Hub ←─webhook─→ Blesta (order.host.uk.com)
         ←──api───→ host_uk module
                    ↓
              Stripe/PayPal
```

### What MixPost Enterprise Already Has
The `packages/mixpost-enterprise` package contains a **complete billing system**:
- Multi-gateway support (Stripe, Paddle, Paystack)
- Subscription lifecycle management
- Plan limits enforcement
- Usage tracking
- Admin panel for billing

**Decision**: Rather than build from scratch, **leverage MixPost Enterprise patterns** or potentially extend its billing system for Host Hub's commerce needs.

---

## Proposed Architecture

### Native Commerce Stack
```
Host Hub
├── Commerce (new Laravel module)
│   ├── Orders
│   ├── Invoices
│   ├── Payments (Stripe, BTCPay)
│   └── Checkout
├── Entitlements (existing)
│   ├── Packages
│   ├── Features
│   ├── WorkspacePackages
│   └── UsageRecords
└── Workspace (existing)
    └── User/Team ownership
```

### Why Not Just Use MixPost Enterprise Billing?
MixPost Enterprise billing is tightly coupled to:
- MixPost Workspace model (not Host Hub's Workspace)
- Social media feature limits
- MixPost-specific subscription states

**Better approach**: Extract patterns and create Host Hub-native commerce that works across ALL modules (BioHost, Analytics, Push, Files, MixPost).

---

## Database Schema

### New Tables

```sql
-- Orders (checkout → payment → fulfillment)
CREATE TABLE orders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    -- Order details
    order_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('pending', 'processing', 'paid', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    type ENUM('new', 'renewal', 'upgrade', 'downgrade', 'addon') NOT NULL,

    -- Financials
    currency VARCHAR(3) NOT NULL DEFAULT 'GBP',
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,

    -- Payment tracking
    payment_method VARCHAR(50) NULL,
    payment_gateway VARCHAR(50) NULL,
    gateway_order_id VARCHAR(255) NULL,

    -- References
    coupon_id BIGINT UNSIGNED NULL,

    -- Metadata
    billing_address JSON NULL,
    metadata JSON NULL,

    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workspace (workspace_id),
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_order_number (order_number),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Order items (line breakdown)
CREATE TABLE order_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,

    -- What was ordered
    item_type ENUM('package', 'addon', 'boost', 'custom') NOT NULL,
    item_id BIGINT UNSIGNED NULL,          -- FK to package/boost depending on type
    item_code VARCHAR(100) NULL,           -- Package or feature code

    -- Details
    description VARCHAR(500) NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,

    -- Billing
    billing_cycle ENUM('monthly', 'yearly', 'onetime', 'lifetime') NOT NULL,

    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Invoices (billing documents)
CREATE TABLE invoices (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,

    -- Invoice details
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'void', 'uncollectible') DEFAULT 'draft',

    -- Financials
    currency VARCHAR(3) NOT NULL DEFAULT 'GBP',
    subtotal DECIMAL(10, 2) NOT NULL,
    tax_amount DECIMAL(10, 2) DEFAULT 0,
    discount_amount DECIMAL(10, 2) DEFAULT 0,
    total DECIMAL(10, 2) NOT NULL,
    amount_paid DECIMAL(10, 2) DEFAULT 0,
    amount_due DECIMAL(10, 2) NOT NULL,

    -- Dates
    issue_date DATE NOT NULL,
    due_date DATE NOT NULL,
    paid_at TIMESTAMP NULL,

    -- Billing info
    billing_name VARCHAR(255) NULL,
    billing_address JSON NULL,
    tax_id VARCHAR(50) NULL,

    -- PDF
    pdf_path VARCHAR(500) NULL,

    -- Auto-billing
    auto_charge BOOLEAN DEFAULT FALSE,
    charge_attempts INT DEFAULT 0,
    last_charge_attempt TIMESTAMP NULL,
    next_charge_attempt TIMESTAMP NULL,

    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workspace (workspace_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- Invoice line items
CREATE TABLE invoice_items (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    invoice_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NULL,

    description VARCHAR(500) NOT NULL,
    quantity INT UNSIGNED DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,

    -- Tax
    taxable BOOLEAN DEFAULT TRUE,
    tax_rate DECIMAL(5, 2) DEFAULT 0,
    tax_amount DECIMAL(10, 2) DEFAULT 0,

    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_invoice (invoice_id),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
);

-- Payments (records of money received)
CREATE TABLE payments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    invoice_id BIGINT UNSIGNED NULL,

    -- Payment details
    gateway VARCHAR(50) NOT NULL,           -- stripe, btcpay, manual, credit
    gateway_payment_id VARCHAR(255) NULL,   -- pi_xxx, inv_xxx, etc.
    gateway_customer_id VARCHAR(255) NULL,  -- cus_xxx

    -- Amount
    currency VARCHAR(3) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    fee DECIMAL(10, 2) DEFAULT 0,           -- Gateway processing fee
    net_amount DECIMAL(10, 2) NOT NULL,     -- amount - fee

    -- Status
    status ENUM('pending', 'processing', 'succeeded', 'failed', 'refunded', 'partially_refunded') DEFAULT 'pending',
    failure_reason VARCHAR(500) NULL,

    -- Payment method details
    payment_method_type VARCHAR(50) NULL,   -- card, crypto, bank_transfer
    payment_method_last4 VARCHAR(4) NULL,
    payment_method_brand VARCHAR(50) NULL,

    -- Gateway raw data
    gateway_response JSON NULL,

    refunded_amount DECIMAL(10, 2) DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_workspace (workspace_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_gateway (gateway, gateway_payment_id),
    INDEX idx_status (status),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL
);

-- Payment methods (saved for recurring)
CREATE TABLE payment_methods (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,

    -- Gateway
    gateway VARCHAR(50) NOT NULL,
    gateway_payment_method_id VARCHAR(255) NOT NULL,
    gateway_customer_id VARCHAR(255) NOT NULL,

    -- Type
    type VARCHAR(50) NOT NULL,              -- card, bank_account, crypto_wallet

    -- Card details (for display)
    brand VARCHAR(50) NULL,
    last_four VARCHAR(4) NULL,
    exp_month TINYINT UNSIGNED NULL,
    exp_year SMALLINT UNSIGNED NULL,

    -- Status
    is_default BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_gateway_method (gateway, gateway_payment_method_id),
    INDEX idx_workspace (workspace_id),
    INDEX idx_user (user_id),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Subscriptions (recurring billing state)
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    workspace_id BIGINT UNSIGNED NOT NULL,
    workspace_package_id BIGINT UNSIGNED NOT NULL,

    -- Gateway subscription
    gateway VARCHAR(50) NOT NULL,
    gateway_subscription_id VARCHAR(255) NOT NULL,
    gateway_customer_id VARCHAR(255) NOT NULL,
    gateway_price_id VARCHAR(255) NULL,

    -- Status
    status ENUM('active', 'trialing', 'past_due', 'paused', 'cancelled', 'incomplete') DEFAULT 'active',

    -- Billing cycle
    current_period_start TIMESTAMP NOT NULL,
    current_period_end TIMESTAMP NOT NULL,

    -- Trial
    trial_ends_at TIMESTAMP NULL,

    -- Cancellation
    cancel_at_period_end BOOLEAN DEFAULT FALSE,
    cancelled_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,

    metadata JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY unique_gateway_sub (gateway, gateway_subscription_id),
    INDEX idx_workspace (workspace_id),
    INDEX idx_status (status),
    INDEX idx_period_end (current_period_end),
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (workspace_package_id) REFERENCES entitlement_workspace_packages(id) ON DELETE CASCADE
);

-- Coupons (discount codes)
CREATE TABLE coupons (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,

    -- Discount type
    type ENUM('percentage', 'fixed_amount') NOT NULL,
    value DECIMAL(10, 2) NOT NULL,          -- 0.20 for 20%, or 500 for £5

    -- Restrictions
    min_amount DECIMAL(10, 2) NULL,         -- Minimum order to apply
    max_discount DECIMAL(10, 2) NULL,       -- Cap for percentage discounts

    -- Applicability
    applies_to ENUM('all', 'packages', 'addons') DEFAULT 'all',
    package_ids JSON NULL,                  -- Specific packages if applies_to = 'packages'

    -- Limits
    max_uses INT UNSIGNED NULL,             -- Total uses allowed
    max_uses_per_workspace INT UNSIGNED DEFAULT 1,
    used_count INT UNSIGNED DEFAULT 0,

    -- Duration
    duration ENUM('once', 'repeating', 'forever') DEFAULT 'once',
    duration_months INT UNSIGNED NULL,      -- For 'repeating'

    -- Validity
    valid_from TIMESTAMP NULL,
    valid_until TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Stripe sync
    stripe_coupon_id VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_code (code),
    INDEX idx_active (is_active)
);

-- Coupon usage tracking
CREATE TABLE coupon_usages (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    coupon_id BIGINT UNSIGNED NOT NULL,
    workspace_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,

    discount_amount DECIMAL(10, 2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_coupon (coupon_id),
    INDEX idx_workspace (workspace_id),
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
    FOREIGN KEY (workspace_id) REFERENCES workspaces(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

-- Tax rates
CREATE TABLE tax_rates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,

    country_code VARCHAR(2) NOT NULL,
    state_code VARCHAR(10) NULL,

    name VARCHAR(100) NOT NULL,             -- "UK VAT", "California Sales Tax"
    type ENUM('vat', 'sales_tax', 'gst') NOT NULL,
    rate DECIMAL(5, 2) NOT NULL,            -- 20.00 for 20%

    -- Digital services special rules
    is_digital_services BOOLEAN DEFAULT TRUE,

    -- Validity
    effective_from DATE NOT NULL,
    effective_until DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,

    -- Stripe sync
    stripe_tax_rate_id VARCHAR(255) NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_country (country_code),
    INDEX idx_active (is_active)
);

-- Refunds
CREATE TABLE refunds (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    payment_id BIGINT UNSIGNED NOT NULL,

    -- Gateway
    gateway_refund_id VARCHAR(255) NULL,

    -- Amount
    amount DECIMAL(10, 2) NOT NULL,
    currency VARCHAR(3) NOT NULL,

    -- Status
    status ENUM('pending', 'succeeded', 'failed', 'cancelled') DEFAULT 'pending',

    reason ENUM('duplicate', 'fraudulent', 'requested_by_customer', 'other') NULL,
    notes TEXT NULL,

    -- Who initiated
    initiated_by BIGINT UNSIGNED NULL,      -- User ID (admin)

    gateway_response JSON NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_payment (payment_id),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);
```

### Extend Existing Tables

```sql
-- Add order reference to workspace packages
ALTER TABLE entitlement_workspace_packages
ADD COLUMN order_id BIGINT UNSIGNED NULL AFTER workspace_id,
ADD COLUMN subscription_id BIGINT UNSIGNED NULL AFTER order_id,
ADD FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
ADD FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL;

-- Add pricing to packages
ALTER TABLE entitlement_packages
ADD COLUMN monthly_price DECIMAL(10, 2) NULL AFTER is_public,
ADD COLUMN yearly_price DECIMAL(10, 2) NULL AFTER monthly_price,
ADD COLUMN setup_fee DECIMAL(10, 2) DEFAULT 0 AFTER yearly_price,
ADD COLUMN trial_days INT UNSIGNED DEFAULT 0 AFTER setup_fee,
ADD COLUMN stripe_monthly_price_id VARCHAR(255) NULL,
ADD COLUMN stripe_yearly_price_id VARCHAR(255) NULL;

-- Add Stripe customer ID to workspaces
ALTER TABLE workspaces
ADD COLUMN stripe_customer_id VARCHAR(255) NULL,
ADD COLUMN billing_email VARCHAR(255) NULL,
ADD COLUMN billing_name VARCHAR(255) NULL,
ADD COLUMN billing_address JSON NULL,
ADD COLUMN tax_id VARCHAR(50) NULL,
ADD INDEX idx_stripe_customer (stripe_customer_id);
```

---

## Service Architecture

### CommerceService
```php
<?php

namespace App\Services\Commerce;

class CommerceService
{
    public function __construct(
        private EntitlementService $entitlements,
        private StripeService $stripe,
        private InvoiceService $invoices,
    ) {}

    /**
     * Create a new order for package purchase
     */
    public function createOrder(
        Workspace $workspace,
        Package $package,
        string $billingCycle,
        ?Coupon $coupon = null
    ): Order {
        // Calculate pricing
        $price = $billingCycle === 'yearly'
            ? $package->yearly_price
            : $package->monthly_price;

        $subtotal = $price;
        $discount = $coupon ? $this->calculateDiscount($coupon, $subtotal) : 0;
        $tax = $this->calculateTax($workspace, $subtotal - $discount);
        $total = $subtotal - $discount + $tax;

        // Create order
        $order = Order::create([
            'workspace_id' => $workspace->id,
            'user_id' => auth()->id(),
            'order_number' => $this->generateOrderNumber(),
            'type' => 'new',
            'currency' => 'GBP',
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'coupon_id' => $coupon?->id,
            'billing_address' => $workspace->billing_address,
        ]);

        // Create order item
        OrderItem::create([
            'order_id' => $order->id,
            'item_type' => 'package',
            'item_id' => $package->id,
            'item_code' => $package->code,
            'description' => "{$package->name} ({$billingCycle})",
            'billing_cycle' => $billingCycle,
            'unit_price' => $price,
            'line_total' => $price,
        ]);

        // Record coupon usage
        if ($coupon) {
            $this->recordCouponUsage($coupon, $workspace, $order, $discount);
        }

        return $order;
    }

    /**
     * Process checkout with Stripe
     */
    public function checkout(Order $order, string $successUrl, string $cancelUrl): string
    {
        // Create or get Stripe customer
        $customer = $this->stripe->getOrCreateCustomer($order->workspace);

        // Create Stripe Checkout Session
        $session = $this->stripe->createCheckoutSession(
            customer: $customer,
            order: $order,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl
        );

        // Store session ID
        $order->update([
            'gateway_order_id' => $session->id,
            'payment_gateway' => 'stripe',
        ]);

        return $session->url;
    }

    /**
     * Handle successful payment (called from webhook)
     */
    public function handlePaymentSuccess(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            // Update order status
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            // Create invoice
            $invoice = $this->invoices->createFromOrder($order);
            $invoice->markAsPaid($payment);

            // Provision entitlements
            foreach ($order->items as $item) {
                if ($item->item_type === 'package') {
                    $this->entitlements->provisionPackage(
                        workspace: $order->workspace,
                        packageCode: $item->item_code,
                        options: [
                            'order_id' => $order->id,
                            'billing_cycle' => $item->billing_cycle,
                        ]
                    );
                } elseif ($item->item_type === 'boost') {
                    $this->entitlements->provisionBoost(
                        workspace: $order->workspace,
                        featureCode: $item->item_code,
                        options: ['order_id' => $order->id]
                    );
                }
            }

            // Send confirmation email
            $order->workspace->owner->notify(new OrderConfirmation($order, $invoice));
        });
    }

    /**
     * Handle subscription renewal (called from webhook)
     */
    public function handleRenewal(Subscription $subscription, Invoice $stripeInvoice): void
    {
        // Create Host Hub invoice
        $invoice = $this->invoices->createFromStripeInvoice($stripeInvoice);

        // Update workspace package dates
        $subscription->workspacePackage->update([
            'billing_cycle_anchor' => $subscription->current_period_start,
            'expires_at' => $subscription->current_period_end,
        ]);

        // Reset cycle-bound boosts
        $this->entitlements->expireCycleBoundBoosts($subscription->workspace);
    }
}
```

### StripeService
```php
<?php

namespace App\Services\Commerce;

use Stripe\StripeClient;

class StripeService
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    public function getOrCreateCustomer(Workspace $workspace): \Stripe\Customer
    {
        if ($workspace->stripe_customer_id) {
            return $this->stripe->customers->retrieve($workspace->stripe_customer_id);
        }

        $customer = $this->stripe->customers->create([
            'email' => $workspace->billing_email ?? $workspace->owner->email,
            'name' => $workspace->billing_name ?? $workspace->name,
            'metadata' => [
                'workspace_id' => $workspace->id,
                'user_id' => $workspace->owner_id,
            ],
        ]);

        $workspace->update(['stripe_customer_id' => $customer->id]);

        return $customer;
    }

    public function createCheckoutSession(
        \Stripe\Customer $customer,
        Order $order,
        string $successUrl,
        string $cancelUrl
    ): \Stripe\Checkout\Session {
        $lineItems = $order->items->map(function ($item) {
            $package = Package::find($item->item_id);
            $priceId = $item->billing_cycle === 'yearly'
                ? $package->stripe_yearly_price_id
                : $package->stripe_monthly_price_id;

            return [
                'price' => $priceId,
                'quantity' => $item->quantity,
            ];
        })->all();

        $params = [
            'customer' => $customer->id,
            'mode' => 'subscription',
            'line_items' => $lineItems,
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'order_id' => $order->id,
                'workspace_id' => $order->workspace_id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'order_id' => $order->id,
                    'workspace_id' => $order->workspace_id,
                ],
            ],
        ];

        // Add coupon if present
        if ($order->coupon && $order->coupon->stripe_coupon_id) {
            $params['discounts'] = [
                ['coupon' => $order->coupon->stripe_coupon_id],
            ];
        }

        // Add trial if package has it
        $package = $order->items->first()?->package;
        if ($package?->trial_days > 0) {
            $params['subscription_data']['trial_period_days'] = $package->trial_days;
        }

        return $this->stripe->checkout->sessions->create($params);
    }

    public function createBillingPortalSession(Workspace $workspace, string $returnUrl): string
    {
        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $workspace->stripe_customer_id,
            'return_url' => $returnUrl,
        ]);

        return $session->url;
    }
}
```

---

## API Routes

```php
// routes/web.php - Public checkout
Route::prefix('checkout')->group(function () {
    Route::get('/', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/create-order', [CheckoutController::class, 'createOrder']);
    Route::get('/success', [CheckoutController::class, 'success'])->name('checkout.success');
    Route::get('/cancel', [CheckoutController::class, 'cancel'])->name('checkout.cancel');
});

// routes/web.php - Billing portal (authenticated)
Route::prefix('hub/billing')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/', [BillingController::class, 'index'])->name('billing.index');
    Route::get('/invoices', [BillingController::class, 'invoices'])->name('billing.invoices');
    Route::get('/invoices/{invoice}/pdf', [BillingController::class, 'downloadInvoice']);
    Route::get('/payment-methods', [BillingController::class, 'paymentMethods']);
    Route::post('/payment-methods', [BillingController::class, 'addPaymentMethod']);
    Route::delete('/payment-methods/{id}', [BillingController::class, 'removePaymentMethod']);
    Route::post('/portal', [BillingController::class, 'stripePortal']);
    Route::post('/change-plan', [BillingController::class, 'changePlan']);
    Route::post('/cancel', [BillingController::class, 'cancelSubscription']);
});

// routes/api.php - Webhooks
Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle']);
    Route::post('/btcpay', [BTCPayWebhookController::class, 'handle']);
});

// routes/api.php - Internal API (for MCP agents)
Route::prefix('v1/commerce')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/orders', [CommerceApiController::class, 'listOrders']);
    Route::get('/invoices', [CommerceApiController::class, 'listInvoices']);
    Route::get('/usage', [CommerceApiController::class, 'getUsage']);
    Route::post('/upgrade', [CommerceApiController::class, 'upgradePlan']);
});
```

---

## Webhook Handler

```php
<?php

namespace App\Http\Controllers\Webhooks;

use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret')
            );
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $method = 'handle' . Str::studly(str_replace('.', '_', $event->type));

        if (method_exists($this, $method)) {
            return $this->$method($event);
        }

        return response()->json(['received' => true]);
    }

    protected function handleCheckoutSessionCompleted($event)
    {
        $session = $event->data->object;
        $orderId = $session->metadata->order_id ?? null;

        if (!$orderId) {
            return response()->json(['error' => 'No order ID'], 400);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Create payment record
        $payment = Payment::create([
            'workspace_id' => $order->workspace_id,
            'gateway' => 'stripe',
            'gateway_payment_id' => $session->payment_intent,
            'gateway_customer_id' => $session->customer,
            'currency' => strtoupper($session->currency),
            'amount' => $session->amount_total / 100,
            'status' => 'succeeded',
            'payment_method_type' => 'card',
        ]);

        // Create subscription record
        if ($session->subscription) {
            $stripeSubscription = $this->stripe->subscriptions->retrieve($session->subscription);

            // ... create local Subscription model
        }

        // Trigger order fulfillment
        app(CommerceService::class)->handlePaymentSuccess($order, $payment);

        return response()->json(['received' => true]);
    }

    protected function handleInvoicePaid($event)
    {
        $stripeInvoice = $event->data->object;
        $subscriptionId = $stripeInvoice->subscription;

        $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return response()->json(['received' => true]); // Not our subscription
        }

        // Handle renewal
        app(CommerceService::class)->handleRenewal($subscription, $stripeInvoice);

        return response()->json(['received' => true]);
    }

    protected function handleInvoicePaymentFailed($event)
    {
        $stripeInvoice = $event->data->object;
        $subscriptionId = $stripeInvoice->subscription;

        $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)->first();
        if (!$subscription) {
            return response()->json(['received' => true]);
        }

        // Update subscription status
        $subscription->update(['status' => 'past_due']);

        // Send dunning email
        $subscription->workspace->owner->notify(new PaymentFailed($subscription));

        // Schedule suspension if payment not recovered
        // (handled by separate cron job checking past_due subscriptions)

        return response()->json(['received' => true]);
    }

    protected function handleCustomerSubscriptionDeleted($event)
    {
        $stripeSubscription = $event->data->object;

        $subscription = Subscription::where('gateway_subscription_id', $stripeSubscription->id)->first();
        if (!$subscription) {
            return response()->json(['received' => true]);
        }

        // Cancel subscription
        $subscription->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        // Cancel workspace package
        app(EntitlementService::class)->cancelPackage(
            $subscription->workspacePackage,
            ['source' => 'stripe']
        );

        return response()->json(['received' => true]);
    }
}
```

---

## MCP Agent Tools

```php
// app/Mcp/Tools/Commerce/GetBillingStatus.php
class GetBillingStatus extends Tool
{
    public function name(): string
    {
        return 'get_billing_status';
    }

    public function description(): string
    {
        return 'Get billing status for a workspace including current plan, usage, and next billing date.';
    }

    public function execute(array $input): array
    {
        $workspace = Workspace::findOrFail($input['workspace_id']);
        $entitlements = app(EntitlementService::class);

        $activePackages = $entitlements->getActivePackages($workspace);
        $subscription = $workspace->subscription;

        return [
            'workspace' => $workspace->name,
            'plan' => $activePackages->first()?->package->name ?? 'None',
            'status' => $subscription?->status ?? 'no_subscription',
            'billing_cycle' => $subscription?->current_period_end?->format('Y-m-d'),
            'next_billing' => $subscription?->current_period_end?->format('Y-m-d'),
            'usage_summary' => $entitlements->getUsageSummary($workspace),
            'invoices_count' => $workspace->invoices()->count(),
            'outstanding_balance' => $workspace->invoices()->where('status', 'sent')->sum('amount_due'),
        ];
    }
}

// app/Mcp/Tools/Commerce/UpgradePlan.php
class UpgradePlan extends Tool
{
    public function name(): string
    {
        return 'upgrade_plan';
    }

    public function description(): string
    {
        return 'Upgrade a workspace to a higher plan. Returns checkout URL for payment.';
    }

    public function execute(array $input): array
    {
        $workspace = Workspace::findOrFail($input['workspace_id']);
        $package = Package::where('code', $input['package_code'])->firstOrFail();
        $billingCycle = $input['billing_cycle'] ?? 'monthly';

        $commerce = app(CommerceService::class);
        $order = $commerce->createOrder($workspace, $package, $billingCycle);
        $checkoutUrl = $commerce->checkout(
            $order,
            route('checkout.success'),
            route('checkout.cancel')
        );

        return [
            'success' => true,
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'checkout_url' => $checkoutUrl,
            'message' => "Order created. Direct user to checkout URL to complete payment.",
        ];
    }
}
```

---

## Migration Strategy

### Phase 1: Foundation (Week 1-2)
1. Create database migrations for commerce tables
2. Build Order, Invoice, Payment, Subscription models
3. Implement StripeService with checkout
4. Create webhook handler for Stripe events
5. Wire up to existing EntitlementService

### Phase 2: Checkout Flow (Week 2-3)
1. Build checkout Livewire component
2. Integrate with pricing page
3. Add coupon/discount handling
4. Create success/failure flows
5. Send confirmation emails

### Phase 3: Billing Portal (Week 3-4)
1. Build billing dashboard
2. Invoice list with PDF downloads
3. Payment method management
4. Plan change flow (upgrade/downgrade)
5. Cancel subscription flow

### Phase 4: Advanced Features (Week 4-5)
1. Tax calculation by region
2. Dunning/retry logic for failed payments
3. Usage-based overage billing
4. Proration for mid-cycle changes
5. Refund processing

### Phase 5: Blesta Migration (Week 5-6)
1. Import existing customer data
2. Sync subscriptions from Blesta
3. Run both systems in parallel
4. Gradual cutover (new customers first)
5. Deprecate Blesta webhooks

---

## Configuration

```php
// config/commerce.php
return [
    'currency' => env('COMMERCE_CURRENCY', 'GBP'),

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'btcpay' => [
        'url' => env('BTCPAY_URL'),
        'store_id' => env('BTCPAY_STORE_ID'),
        'api_key' => env('BTCPAY_API_KEY'),
    ],

    'billing' => [
        'invoice_prefix' => env('INVOICE_PREFIX', 'INV-'),
        'invoice_start_number' => env('INVOICE_START_NUMBER', 1000),
        'tax_enabled' => env('TAX_ENABLED', true),
        'default_tax_rate' => env('DEFAULT_TAX_RATE', 20), // UK VAT
    ],

    'dunning' => [
        'retry_days' => [3, 5, 7], // Retry payment on these days after failure
        'suspend_after_days' => 14, // Suspend service after X days unpaid
        'cancel_after_days' => 30, // Cancel service after X days suspended
    ],

    'trials' => [
        'default_days' => 14,
        'require_payment_method' => false,
    ],
];
```

---

## Summary

Native commerce for Host Hub will:
1. **Eliminate Blesta dependency** - No external billing system
2. **Leverage existing entitlements** - Works with current Package/Feature system
3. **Enable tighter integration** - Direct connection to all Host Hub services
4. **Support MCP agents** - Commerce tools for automated billing operations
5. **Reduce complexity** - Single Laravel codebase for everything

The architecture builds on proven patterns from both Blesta and MixPost Enterprise, adapted for Host Hub's specific needs.