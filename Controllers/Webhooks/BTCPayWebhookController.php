<?php

namespace Core\Mod\Commerce\Controllers\Webhooks;

use Core\Front\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Core\Mod\Commerce\Models\Order;
use Core\Mod\Commerce\Models\Payment;
use Core\Mod\Commerce\Notifications\OrderConfirmation;
use Core\Mod\Commerce\Services\CommerceService;
use Core\Mod\Commerce\Services\PaymentGateway\BTCPayGateway;
use Core\Mod\Commerce\Services\WebhookLogger;

/**
 * Handle BTCPay Server webhooks.
 *
 * BTCPay sends webhooks for invoice state changes:
 * - InvoiceCreated: Invoice was created
 * - InvoiceReceivedPayment: Payment detected (0 confirmations)
 * - InvoiceProcessing: Payment processing (waiting for confirmations)
 * - InvoiceExpired: Invoice expired without full payment
 * - InvoiceSettled: Payment fully confirmed
 * - InvoiceInvalid: Payment was invalid/rejected
 */
class BTCPayWebhookController extends Controller
{
    public function __construct(
        protected BTCPayGateway $gateway,
        protected CommerceService $commerce,
        protected WebhookLogger $webhookLogger,
    ) {}

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('BTCPay-Sig');

        // Verify webhook signature
        if (! $this->gateway->verifyWebhookSignature($payload, $signature)) {
            Log::warning('BTCPay webhook signature verification failed');

            return response('Invalid signature', 401);
        }

        $event = $this->gateway->parseWebhookEvent($payload);

        // Log the webhook event for audit trail
        $this->webhookLogger->startFromParsedEvent('btcpay', $event, $payload, $request);

        Log::info('BTCPay webhook received', [
            'type' => $event['type'],
            'id' => $event['id'],
        ]);

        try {
            // Wrap all webhook processing in a transaction to ensure data integrity
            $response = DB::transaction(function () use ($event) {
                return match ($event['type']) {
                    'invoice.created' => $this->handleInvoiceCreated($event),
                    'invoice.payment_received' => $this->handlePaymentReceived($event),
                    'invoice.processing' => $this->handleProcessing($event),
                    'invoice.paid', 'payment.settled' => $this->handleSettled($event),
                    'invoice.expired' => $this->handleExpired($event),
                    'invoice.failed' => $this->handleFailed($event),
                    default => $this->handleUnknownEvent($event),
                };
            });

            $this->webhookLogger->success($response);

            return $response;
        } catch (\Exception $e) {
            Log::error('BTCPay webhook processing error', [
                'type' => $event['type'],
                'error' => $e->getMessage(),
            ]);

            $this->webhookLogger->fail($e->getMessage(), 500);

            return response('Processing error', 500);
        }
    }

    protected function handleUnknownEvent(array $event): Response
    {
        $this->webhookLogger->skip('Unhandled event type: '.$event['type']);

        return response('Unhandled event type', 200);
    }

    protected function handleInvoiceCreated(array $event): Response
    {
        // Invoice created - no action needed
        return response('OK', 200);
    }

    protected function handlePaymentReceived(array $event): Response
    {
        // Payment detected but not confirmed
        $order = $this->findOrderByInvoiceId($event['id']);

        if ($order) {
            // Update order status to show payment is incoming
            $order->update(['status' => 'processing']);
        }

        return response('OK', 200);
    }

    protected function handleProcessing(array $event): Response
    {
        // Payment is being processed (waiting for confirmations)
        $order = $this->findOrderByInvoiceId($event['id']);

        if ($order) {
            $order->update(['status' => 'processing']);
        }

        return response('OK', 200);
    }

    protected function handleSettled(array $event): Response
    {
        // Payment fully confirmed - fulfil the order
        $order = $this->findOrderByInvoiceId($event['id']);

        if (! $order) {
            Log::warning('BTCPay webhook: Order not found', ['invoice_id' => $event['id']]);

            return response('Order not found', 200);
        }

        // Link webhook event to order for audit trail
        $this->webhookLogger->linkOrder($order);

        // Skip if already paid
        if ($order->isPaid()) {
            return response('Already processed', 200);
        }

        // Get invoice details from BTCPay
        $invoiceData = $this->gateway->getCheckoutSession($event['id']);

        // Create payment record
        $payment = Payment::create([
            'workspace_id' => $order->workspace_id,
            'order_id' => $order->id,
            'invoice_id' => null, // Will be set by fulfillOrder
            'gateway' => 'btcpay',
            'gateway_payment_id' => $event['id'],
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'succeeded',
            'paid_at' => now(),
            'gateway_response' => $invoiceData['raw'] ?? [],
        ]);

        // Fulfil the order (provisions entitlements, creates invoice)
        $this->commerce->fulfillOrder($order, $payment);

        // Send confirmation email
        $this->sendOrderConfirmation($order);

        Log::info('BTCPay order fulfilled', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'payment_id' => $payment->id,
        ]);

        return response('OK', 200);
    }

    protected function handleExpired(array $event): Response
    {
        // Invoice expired - mark order as failed
        $order = $this->findOrderByInvoiceId($event['id']);

        if ($order && ! $order->isPaid()) {
            $order->markAsFailed('Payment expired');
        }

        return response('OK', 200);
    }

    protected function handleFailed(array $event): Response
    {
        // Payment invalid/rejected
        $order = $this->findOrderByInvoiceId($event['id']);

        if ($order && ! $order->isPaid()) {
            $order->markAsFailed('Payment rejected');
        }

        return response('OK', 200);
    }

    protected function findOrderByInvoiceId(string $invoiceId): ?Order
    {
        return Order::where('gateway', 'btcpay')
            ->where('gateway_session_id', $invoiceId)
            ->first();
    }

    protected function sendOrderConfirmation(Order $order): void
    {
        if (! config('commerce.notifications.order_confirmation', true)) {
            return;
        }

        // Use resolved workspace to handle both Workspace and User orderables
        $workspace = $order->getResolvedWorkspace();
        $owner = $workspace?->owner();
        if ($owner) {
            $owner->notify(new OrderConfirmation($order));
        }
    }
}
