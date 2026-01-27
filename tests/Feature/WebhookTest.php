<?php

use Illuminate\Support\Facades\Notification;
use Core\Commerce\Controllers\Webhooks\BTCPayWebhookController;
use Core\Commerce\Controllers\Webhooks\StripeWebhookController;
use Core\Commerce\Models\Order;
use Core\Commerce\Models\OrderItem;
use Core\Commerce\Models\Payment;
use Core\Commerce\Models\Subscription;
use Core\Commerce\Models\WebhookEvent;
use Core\Commerce\Notifications\PaymentFailed;
use Core\Commerce\Notifications\SubscriptionCancelled;
use Core\Commerce\Services\CommerceService;
use Core\Commerce\Services\InvoiceService;
use Core\Commerce\Services\PaymentGateway\BTCPayGateway;
use Core\Commerce\Services\PaymentGateway\StripeGateway;
use Core\Commerce\Services\WebhookLogger;
use Core\Mod\Tenant\Models\Package;
use Core\Mod\Tenant\Models\User;
use Core\Mod\Tenant\Models\Workspace;
use Core\Mod\Tenant\Models\WorkspacePackage;
use Core\Mod\Tenant\Services\EntitlementService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();

    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create([
        'stripe_customer_id' => 'cus_test_123',
        'btcpay_customer_id' => 'btc_cus_test_123',
    ]);
    $this->workspace->users()->attach($this->user->id, [
        'role' => 'owner',
        'is_default' => true,
    ]);
});

// ============================================================================
// Stripe Webhook Tests
// ============================================================================

describe('StripeWebhookController', function () {
    beforeEach(function () {
        $this->order = Order::create([
            'workspace_id' => $this->workspace->id,
            'order_number' => 'ORD-TEST-001',
            'gateway' => 'stripe',
            'gateway_session_id' => 'cs_test_123',
            'subtotal' => 49.00,
            'tax_amount' => 9.80,
            'total' => 58.80,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'name' => 'Creator Plan',
            'description' => 'Monthly subscription',
            'quantity' => 1,
            'unit_price' => 49.00,
            'total' => 49.00,
            'type' => 'package',
        ]);
    });

    describe('signature verification', function () {
        it('rejects requests with invalid signature', function () {
            $response = $this->postJson(route('api.webhook.stripe'), [
                'type' => 'checkout.session.completed',
            ], [
                'Stripe-Signature' => 'invalid_signature',
            ]);

            $response->assertStatus(401);
        });

        it('rejects requests without signature', function () {
            $response = $this->postJson(route('api.webhook.stripe'), [
                'type' => 'checkout.session.completed',
            ]);

            $response->assertStatus(401);
        });
    });

    describe('checkout.session.completed event', function () {
        it('fulfils order on successful checkout', function () {
            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'checkout.session.completed',
                'id' => 'cs_test_123',
                'metadata' => ['order_id' => $this->order->id],
                'raw' => [
                    'data' => [
                        'object' => [
                            'id' => 'cs_test_123',
                            'payment_intent' => 'pi_test_123',
                            'amount_total' => 5880,
                            'currency' => 'gbp',
                            'metadata' => ['order_id' => $this->order->id],
                        ],
                    ],
                ],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockCommerce->shouldReceive('fulfillOrder')->once();

            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);

            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $request->headers->set('Stripe-Signature', 't=123,v1=abc');

            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            // Verify payment was created
            $payment = Payment::where('order_id', $this->order->id)->first();
            expect($payment)->not->toBeNull()
                ->and($payment->gateway)->toBe('stripe')
                ->and($payment->status)->toBe('succeeded');

            // Verify webhook event was logged
            $webhookEvent = WebhookEvent::forGateway('stripe')->latest()->first();
            expect($webhookEvent)->not->toBeNull()
                ->and($webhookEvent->event_type)->toBe('checkout.session.completed')
                ->and($webhookEvent->status)->toBe(WebhookEvent::STATUS_PROCESSED);
        });

        it('skips already paid orders', function () {
            $this->order->update(['status' => 'paid', 'paid_at' => now()]);

            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'checkout.session.completed',
                'id' => 'cs_test_123',
                'raw' => [
                    'data' => [
                        'object' => [
                            'id' => 'cs_test_123',
                            'metadata' => ['order_id' => $this->order->id],
                        ],
                    ],
                ],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockCommerce->shouldNotReceive('fulfillOrder');

            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Already processed');
        });

        it('handles missing order gracefully', function () {
            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'checkout.session.completed',
                'id' => 'cs_test_123',
                'raw' => [
                    'data' => [
                        'object' => [
                            'id' => 'cs_test_123',
                            'metadata' => ['order_id' => 99999],
                        ],
                    ],
                ],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Order not found');
        });
    });

    describe('invoice.payment_failed event', function () {
        it('marks subscription as past due and notifies owner', function () {
            $package = Package::where('code', 'creator')->first();
            $workspacePackage = WorkspacePackage::create([
                'workspace_id' => $this->workspace->id,
                'package_id' => $package->id,
                'status' => 'active',
            ]);

            $subscription = Subscription::create([
                'workspace_id' => $this->workspace->id,
                'workspace_package_id' => $workspacePackage->id,
                'gateway' => 'stripe',
                'gateway_subscription_id' => 'sub_test_123',
                'gateway_customer_id' => 'cus_test_123',
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.payment_failed',
                'id' => 'in_test_123',
                'raw' => [
                    'data' => [
                        'object' => [
                            'id' => 'in_test_123',
                            'subscription' => 'sub_test_123',
                        ],
                    ],
                ],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $subscription->refresh();
            expect($subscription->status)->toBe('past_due');

            Notification::assertSentTo($this->user, PaymentFailed::class);
        });
    });

    describe('customer.subscription.deleted event', function () {
        it('cancels subscription and revokes entitlements', function () {
            $package = Package::where('code', 'creator')->first();
            $workspacePackage = WorkspacePackage::create([
                'workspace_id' => $this->workspace->id,
                'package_id' => $package->id,
                'status' => 'active',
            ]);

            $subscription = Subscription::create([
                'workspace_id' => $this->workspace->id,
                'workspace_package_id' => $workspacePackage->id,
                'gateway' => 'stripe',
                'gateway_subscription_id' => 'sub_test_456',
                'gateway_customer_id' => 'cus_test_123',
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'customer.subscription.deleted',
                'id' => 'sub_test_456',
                'raw' => [
                    'data' => [
                        'object' => [
                            'id' => 'sub_test_456',
                            'status' => 'canceled',
                        ],
                    ],
                ],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);
            $mockEntitlements->shouldReceive('revokePackage')->once();
            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $subscription->refresh();
            expect($subscription->status)->toBe('cancelled')
                ->and($subscription->ended_at)->not->toBeNull();

            Notification::assertSentTo($this->user, SubscriptionCancelled::class);
        });
    });

    describe('unhandled events', function () {
        it('returns 200 for unknown event types', function () {
            $mockGateway = Mockery::mock(StripeGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'some.unknown.event',
                'id' => 'evt_test_123',
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockInvoice = Mockery::mock(InvoiceService::class);
            $mockEntitlements = Mockery::mock(EntitlementService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new StripeWebhookController(
                $mockGateway,
                $mockCommerce,
                $mockInvoice,
                $mockEntitlements,
                $webhookLogger
            );

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Unhandled event type');

            // Verify webhook event was logged as skipped
            $webhookEvent = WebhookEvent::forGateway('stripe')->latest()->first();
            expect($webhookEvent)->not->toBeNull()
                ->and($webhookEvent->status)->toBe(WebhookEvent::STATUS_SKIPPED);
        });
    });
});

// ============================================================================
// BTCPay Webhook Tests
// ============================================================================

describe('BTCPayWebhookController', function () {
    beforeEach(function () {
        $this->order = Order::create([
            'workspace_id' => $this->workspace->id,
            'order_number' => 'ORD-BTC-001',
            'gateway' => 'btcpay',
            'gateway_session_id' => 'btc_invoice_123',
            'subtotal' => 49.00,
            'tax_amount' => 9.80,
            'total' => 58.80,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'name' => 'Creator Plan',
            'description' => 'Monthly subscription',
            'quantity' => 1,
            'unit_price' => 49.00,
            'total' => 49.00,
            'type' => 'package',
        ]);
    });

    describe('signature verification', function () {
        it('rejects requests with invalid signature', function () {
            $response = $this->postJson(route('api.webhook.btcpay'), [
                'type' => 'InvoiceSettled',
            ], [
                'BTCPay-Sig' => 'invalid_signature',
            ]);

            $response->assertStatus(401);
        });

        it('rejects requests without signature', function () {
            $response = $this->postJson(route('api.webhook.btcpay'), [
                'type' => 'InvoiceSettled',
            ]);

            $response->assertStatus(401);
        });
    });

    describe('invoice.paid event (InvoiceSettled)', function () {
        it('fulfils order on successful payment', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.paid',
                'id' => 'btc_invoice_123',
                'status' => 'succeeded',
                'metadata' => [],
                'raw' => [
                    'invoiceId' => 'btc_invoice_123',
                    'type' => 'InvoiceSettled',
                ],
            ]);
            $mockGateway->shouldReceive('getCheckoutSession')->andReturn([
                'id' => 'btc_invoice_123',
                'status' => 'succeeded',
                'amount' => 58.80,
                'currency' => 'GBP',
                'raw' => ['invoiceId' => 'btc_invoice_123'],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockCommerce->shouldReceive('fulfillOrder')->once();

            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $request->headers->set('BTCPay-Sig', 'valid_signature');

            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            // Verify payment was created
            $payment = Payment::where('order_id', $this->order->id)->first();
            expect($payment)->not->toBeNull()
                ->and($payment->gateway)->toBe('btcpay')
                ->and($payment->status)->toBe('succeeded');

            // Verify webhook event was logged
            $webhookEvent = WebhookEvent::forGateway('btcpay')->latest()->first();
            expect($webhookEvent)->not->toBeNull()
                ->and($webhookEvent->event_type)->toBe('invoice.paid')
                ->and($webhookEvent->status)->toBe(WebhookEvent::STATUS_PROCESSED);
        });

        it('skips already paid orders', function () {
            $this->order->update(['status' => 'paid', 'paid_at' => now()]);

            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.paid',
                'id' => 'btc_invoice_123',
                'status' => 'succeeded',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $mockCommerce->shouldNotReceive('fulfillOrder');

            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Already processed');
        });

        it('handles missing order gracefully', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.paid',
                'id' => 'btc_invoice_nonexistent',
                'status' => 'succeeded',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Order not found');
        });
    });

    describe('invoice.expired event', function () {
        it('marks order as failed when invoice expires', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.expired',
                'id' => 'btc_invoice_123',
                'status' => 'expired',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $this->order->refresh();
            expect($this->order->status)->toBe('failed');
        });

        it('does not mark paid orders as failed', function () {
            $this->order->update(['status' => 'paid', 'paid_at' => now()]);

            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.expired',
                'id' => 'btc_invoice_123',
                'status' => 'expired',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $this->order->refresh();
            expect($this->order->status)->toBe('paid');
        });
    });

    describe('invoice.failed event', function () {
        it('marks order as failed when payment is rejected', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.failed',
                'id' => 'btc_invoice_123',
                'status' => 'failed',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $this->order->refresh();
            expect($this->order->status)->toBe('failed');
        });
    });

    describe('invoice.processing event', function () {
        it('marks order as processing when payment is being confirmed', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.processing',
                'id' => 'btc_invoice_123',
                'status' => 'processing',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $this->order->refresh();
            expect($this->order->status)->toBe('processing');
        });
    });

    describe('invoice.payment_received event', function () {
        it('marks order as processing when payment is detected', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'invoice.payment_received',
                'id' => 'btc_invoice_123',
                'status' => 'processing',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200);

            $this->order->refresh();
            expect($this->order->status)->toBe('processing');
        });
    });

    describe('unhandled events', function () {
        it('returns 200 for unknown event types', function () {
            $mockGateway = Mockery::mock(BTCPayGateway::class);
            $mockGateway->shouldReceive('verifyWebhookSignature')->andReturn(true);
            $mockGateway->shouldReceive('parseWebhookEvent')->andReturn([
                'type' => 'some.unknown.event',
                'id' => 'btc_invoice_123',
                'status' => 'unknown',
                'metadata' => [],
                'raw' => [],
            ]);

            $mockCommerce = Mockery::mock(CommerceService::class);
            $webhookLogger = new WebhookLogger;

            $controller = new BTCPayWebhookController($mockGateway, $mockCommerce, $webhookLogger);

            $request = new \Illuminate\Http\Request;
            $response = $controller->handle($request);

            expect($response->getStatusCode())->toBe(200)
                ->and($response->getContent())->toBe('Unhandled event type');

            // Verify webhook event was logged as skipped
            $webhookEvent = WebhookEvent::forGateway('btcpay')->latest()->first();
            expect($webhookEvent)->not->toBeNull()
                ->and($webhookEvent->status)->toBe(WebhookEvent::STATUS_SKIPPED);
        });
    });
});

// ============================================================================
// Webhook Event Logging Tests
// ============================================================================

describe('WebhookEvent model', function () {
    it('creates webhook event with all fields', function () {
        $event = WebhookEvent::record(
            gateway: 'stripe',
            eventType: 'checkout.session.completed',
            payload: '{"test": true}',
            eventId: 'evt_test_123',
            headers: ['Content-Type' => 'application/json']
        );

        expect($event)->toBeInstanceOf(WebhookEvent::class)
            ->and($event->gateway)->toBe('stripe')
            ->and($event->event_type)->toBe('checkout.session.completed')
            ->and($event->event_id)->toBe('evt_test_123')
            ->and($event->payload)->toBe('{"test": true}')
            ->and($event->headers)->toBe(['Content-Type' => 'application/json'])
            ->and($event->status)->toBe(WebhookEvent::STATUS_PENDING)
            ->and($event->received_at)->not->toBeNull();
    });

    it('marks event as processed', function () {
        $event = WebhookEvent::record('stripe', 'test.event', '{}');
        $event->markProcessed(200);

        expect($event->status)->toBe(WebhookEvent::STATUS_PROCESSED)
            ->and($event->http_status_code)->toBe(200)
            ->and($event->processed_at)->not->toBeNull();
    });

    it('marks event as failed with error', function () {
        $event = WebhookEvent::record('stripe', 'test.event', '{}');
        $event->markFailed('Something went wrong', 500);

        expect($event->status)->toBe(WebhookEvent::STATUS_FAILED)
            ->and($event->error_message)->toBe('Something went wrong')
            ->and($event->http_status_code)->toBe(500)
            ->and($event->processed_at)->not->toBeNull();
    });

    it('marks event as skipped with reason', function () {
        $event = WebhookEvent::record('stripe', 'test.event', '{}');
        $event->markSkipped('Unhandled event type');

        expect($event->status)->toBe(WebhookEvent::STATUS_SKIPPED)
            ->and($event->error_message)->toBe('Unhandled event type')
            ->and($event->http_status_code)->toBe(200);
    });

    it('checks for duplicate events', function () {
        WebhookEvent::record('stripe', 'test.event', '{}', 'evt_unique_123')
            ->markProcessed();

        expect(WebhookEvent::hasBeenProcessed('stripe', 'evt_unique_123'))->toBeTrue()
            ->and(WebhookEvent::hasBeenProcessed('stripe', 'evt_other'))->toBeFalse()
            ->and(WebhookEvent::hasBeenProcessed('btcpay', 'evt_unique_123'))->toBeFalse();
    });

    it('links to order and subscription', function () {
        $order = Order::create([
            'workspace_id' => $this->workspace->id,
            'order_number' => 'ORD-LINK-001',
            'subtotal' => 10.00,
            'total' => 10.00,
            'currency' => 'GBP',
            'status' => 'pending',
        ]);

        $event = WebhookEvent::record('stripe', 'test.event', '{}');
        $event->linkOrder($order);

        expect($event->order_id)->toBe($order->id)
            ->and($event->order)->toBeInstanceOf(Order::class);
    });

    it('decodes payload correctly', function () {
        $event = WebhookEvent::record('stripe', 'test.event', '{"key": "value", "nested": {"a": 1}}');

        expect($event->getDecodedPayload())->toBe([
            'key' => 'value',
            'nested' => ['a' => 1],
        ]);
    });

    it('scopes by gateway and status', function () {
        WebhookEvent::record('stripe', 'evt.1', '{}')->markProcessed();
        WebhookEvent::record('stripe', 'evt.2', '{}')->markFailed('err');
        WebhookEvent::record('btcpay', 'evt.3', '{}')->markProcessed();

        expect(WebhookEvent::forGateway('stripe')->count())->toBe(2)
            ->and(WebhookEvent::forGateway('btcpay')->count())->toBe(1)
            ->and(WebhookEvent::failed()->count())->toBe(1);
    });
});

describe('WebhookLogger service', function () {
    it('starts and completes webhook logging', function () {
        $logger = new WebhookLogger;

        $event = $logger->start(
            gateway: 'stripe',
            eventType: 'checkout.session.completed',
            payload: '{"data": "test"}',
            eventId: 'evt_logger_test'
        );

        expect($event->status)->toBe(WebhookEvent::STATUS_PENDING);

        $logger->success();

        $event->refresh();
        expect($event->status)->toBe(WebhookEvent::STATUS_PROCESSED);
    });

    it('handles failures correctly', function () {
        $logger = new WebhookLogger;

        $logger->start('btcpay', 'invoice.paid', '{}');
        $logger->fail('Database error', 500);

        $event = $logger->getCurrentEvent();
        expect($event->status)->toBe(WebhookEvent::STATUS_FAILED)
            ->and($event->error_message)->toBe('Database error')
            ->and($event->http_status_code)->toBe(500);
    });

    it('detects duplicate events', function () {
        $logger = new WebhookLogger;

        // First event
        $logger->start('stripe', 'test.event', '{}', 'evt_dup_test');
        $logger->success();

        // Check for duplicate
        expect($logger->isDuplicate('stripe', 'evt_dup_test'))->toBeTrue();
    });

    it('extracts relevant headers', function () {
        $logger = new WebhookLogger;

        $request = new \Illuminate\Http\Request;
        $request->headers->set('Stripe-Signature', 't=123,v1=secret_signature_here');
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('User-Agent', 'Stripe/1.0');

        $event = $logger->start('stripe', 'test.event', '{}', null, $request);

        expect($event->headers)->toHaveKey('Content-Type')
            ->and($event->headers)->toHaveKey('User-Agent')
            ->and($event->headers)->toHaveKey('Stripe-Signature');

        // Signature should be masked
        expect($event->headers['Stripe-Signature'])->toContain('...');
    });

    it('gets statistics for webhook events', function () {
        $logger = new WebhookLogger;

        // Create some events
        WebhookEvent::record('stripe', 'evt.1', '{}')->markProcessed();
        WebhookEvent::record('stripe', 'evt.2', '{}')->markProcessed();
        WebhookEvent::record('stripe', 'evt.3', '{}')->markFailed('err');
        WebhookEvent::record('stripe', 'evt.4', '{}')->markSkipped('skip');

        $stats = $logger->getStats('stripe');

        expect($stats['total'])->toBe(4)
            ->and($stats['processed'])->toBe(2)
            ->and($stats['failed'])->toBe(1)
            ->and($stats['skipped'])->toBe(1);
    });
});

// ============================================================================
// Gateway Webhook Signature Tests
// ============================================================================

describe('BTCPayGateway webhook signature verification', function () {
    it('verifies correct HMAC signature', function () {
        config(['commerce.gateways.btcpay.webhook_secret' => 'test_secret_123']);

        $gateway = new BTCPayGateway;
        $payload = '{"type":"InvoiceSettled","invoiceId":"123"}';
        $signature = hash_hmac('sha256', $payload, 'test_secret_123');

        expect($gateway->verifyWebhookSignature($payload, $signature))->toBeTrue();
    });

    it('verifies signature with sha256= prefix', function () {
        config(['commerce.gateways.btcpay.webhook_secret' => 'test_secret_123']);

        $gateway = new BTCPayGateway;
        $payload = '{"type":"InvoiceSettled","invoiceId":"123"}';
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'test_secret_123');

        expect($gateway->verifyWebhookSignature($payload, $signature))->toBeTrue();
    });

    it('rejects invalid signature', function () {
        config(['commerce.gateways.btcpay.webhook_secret' => 'test_secret_123']);

        $gateway = new BTCPayGateway;
        $payload = '{"type":"InvoiceSettled","invoiceId":"123"}';

        expect($gateway->verifyWebhookSignature($payload, 'invalid'))->toBeFalse();
    });

    it('rejects empty signature', function () {
        config(['commerce.gateways.btcpay.webhook_secret' => 'test_secret_123']);

        $gateway = new BTCPayGateway;
        $payload = '{"type":"InvoiceSettled","invoiceId":"123"}';

        expect($gateway->verifyWebhookSignature($payload, ''))->toBeFalse();
    });

    it('rejects when no webhook secret configured', function () {
        config(['commerce.gateways.btcpay.webhook_secret' => null]);

        $gateway = new BTCPayGateway;
        $payload = '{"type":"InvoiceSettled","invoiceId":"123"}';
        $signature = hash_hmac('sha256', $payload, 'any_secret');

        expect($gateway->verifyWebhookSignature($payload, $signature))->toBeFalse();
    });
});

describe('BTCPayGateway webhook event parsing', function () {
    it('parses valid webhook payload', function () {
        $gateway = new BTCPayGateway;
        $payload = json_encode([
            'type' => 'InvoiceSettled',
            'invoiceId' => 'inv_123',
            'status' => 'Settled',
            'metadata' => ['order_id' => 1],
        ]);

        $event = $gateway->parseWebhookEvent($payload);

        expect($event['type'])->toBe('invoice.paid')
            ->and($event['id'])->toBe('inv_123')
            ->and($event['status'])->toBe('succeeded')
            ->and($event['metadata'])->toBe(['order_id' => 1]);
    });

    it('handles invalid JSON gracefully', function () {
        $gateway = new BTCPayGateway;
        $payload = 'invalid json {{{';

        $event = $gateway->parseWebhookEvent($payload);

        expect($event['type'])->toBe('unknown')
            ->and($event['id'])->toBeNull()
            ->and($event['raw'])->toBe([]);
    });

    it('maps event types correctly', function () {
        $gateway = new BTCPayGateway;

        $testCases = [
            ['type' => 'InvoiceCreated', 'expected' => 'invoice.created'],
            ['type' => 'InvoiceReceivedPayment', 'expected' => 'invoice.payment_received'],
            ['type' => 'InvoiceProcessing', 'expected' => 'invoice.processing'],
            ['type' => 'InvoiceExpired', 'expected' => 'invoice.expired'],
            ['type' => 'InvoiceSettled', 'expected' => 'invoice.paid'],
            ['type' => 'InvoiceInvalid', 'expected' => 'invoice.failed'],
            ['type' => 'InvoicePaymentSettled', 'expected' => 'payment.settled'],
        ];

        foreach ($testCases as $case) {
            $event = $gateway->parseWebhookEvent(json_encode(['type' => $case['type']]));
            expect($event['type'])->toBe($case['expected'], "Failed for type: {$case['type']}");
        }
    });

    it('maps invoice statuses correctly', function () {
        $gateway = new BTCPayGateway;

        $testCases = [
            ['status' => 'New', 'expected' => 'pending'],
            ['status' => 'Processing', 'expected' => 'processing'],
            ['status' => 'Expired', 'expected' => 'expired'],
            ['status' => 'Invalid', 'expected' => 'failed'],
            ['status' => 'Settled', 'expected' => 'succeeded'],
            ['status' => 'Complete', 'expected' => 'succeeded'],
        ];

        foreach ($testCases as $case) {
            $event = $gateway->parseWebhookEvent(json_encode([
                'type' => 'InvoiceSettled',
                'status' => $case['status'],
            ]));
            expect($event['status'])->toBe($case['expected'], "Failed for status: {$case['status']}");
        }
    });
});

afterEach(function () {
    Mockery::close();
});
