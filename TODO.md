# TODO.md - core-commerce

Production-quality task list for the commerce module.

---

## P1 - Critical / Security

### Webhook Security

- [x] **Add idempotency handling for BTCPay webhooks** - ~~Currently `BTCPayWebhookController::handleSettled()` checks `$order->isPaid()` but doesn't record processed webhook IDs. A replay attack could trigger duplicate processing if timing is right.~~ **FIXED:** Added `isAlreadyProcessed()` method in both `BTCPayWebhookController` and `StripeWebhookController`. Webhook events are now stored in `webhook_events` table with unique constraint on `(gateway, event_id)`. Duplicate events are rejected early with "Already processed (duplicate)" response. Migration: `2026_01_29_000001_create_webhook_events_table.php`.

- [x] **Add rate limiting per IP for webhook endpoints** - ~~Current throttle (120/min) is global. A malicious actor could exhaust the limit for legitimate webhooks. Add per-IP limiting with higher limits for known gateway IPs.~~ **FIXED (2026-01-29):** Added `WebhookRateLimiter` service with per-IP rate limiting. Default: 60 requests/minute for unknown IPs, 300/minute for trusted gateway IPs. Supports CIDR ranges for IP allowlisting. Both `StripeWebhookController` and `BTCPayWebhookController` now check rate limits before processing, returning 429 with `Retry-After` header when exceeded. Configuration in `config.php` under `webhooks.rate_limits` and `webhooks.trusted_ips`.

- [x] **Validate BTCPay webhook payload structure** - ~~`parseWebhookEvent()` assumes JSON structure without schema validation. Malformed payloads could cause unexpected behaviour.~~ **FIXED (P2-076):** Added comprehensive payload validation to `BTCPayGateway::parseWebhookEvent()`. Validates: 1) JSON syntax 2) Required fields (type, invoiceId/id) 3) Field types (string, numeric, object) 4) Field values (non-empty, length limits, currency format, non-negative amounts). Invalid payloads throw `WebhookPayloadValidationException` with detailed error info. Controller returns 400 with error message and logs validation failures for security auditing.

- [x] **Add webhook replay protection window** - ~~Neither gateway stores processed webhook event IDs with timestamp-based expiry.~~ **FIXED:** Webhook events are now stored permanently in `webhook_events` table with `processed_at` timestamp. Both controllers check for existing processed events before reprocessing. The unique constraint prevents race conditions at the database level.

### Payment Security

- [x] **Add amount verification for BTCPay settlements** - ~~`BTCPayWebhookController::handleSettled()` trusts the order's `total` without verifying against BTCPay's settled amount.~~ **FIXED:** Added `verifyPaymentAmount()` method that checks: 1) Currency matches order currency 2) Paid amount >= order total. Underpayments are rejected and order marked as failed with detailed reason. Overpayments are logged but fulfilled. Payment records include actual paid amount for audit trail.

- [x] **Add currency mismatch detection** - ~~If gateway returns different currency than order, this could result in incorrect fulfillment.~~ **FIXED:** The `verifyPaymentAmount()` method now validates currency matches. Orders with currency mismatch are marked as failed with "Currency mismatch: received X, expected Y" message.

- [x] **Rate limit checkout session creation** - ~~`CheckoutRateLimiter` exists but isn't applied in `CommerceService::createCheckout()`. Card testing attacks could abuse this endpoint.~~ **FIXED:** `CheckoutRateLimiter` is already integrated into `CommerceService::createCheckout()` via the `enforceCheckoutRateLimit()` method. Limits are 5 attempts per 15-minute window per workspace/user/IP. `CheckoutRateLimitException` thrown when exceeded.

- [x] **Add fraud scoring integration** - ~~No fraud detection for suspicious patterns (multiple failed payments, velocity checks, geo-anomalies). Consider Stripe Radar integration for Stripe gateway.~~ **FIXED (2026-01-29):** Integrated `FraudService` into checkout flow. Pre-checkout assessment performs velocity checks (orders per IP/email, failed payments per workspace) and geo-anomaly detection (country mismatch, high-risk countries). Post-payment Stripe Radar outcomes are processed via `charge.succeeded` and `payment_intent.succeeded` webhooks. High-risk orders are blocked with `FraudBlockedException`. Elevated-risk orders are flagged for review. Fraud assessments stored in order/payment metadata.

### Input Validation

- [x] **Sanitise user-provided coupon codes** - ~~`CouponService::validateByCode()` uses raw input. Add length limits, character validation, and normalisation (uppercase) before DB query.~~ **FIXED (2026-01-29):** Added `sanitiseCode()` method to `CouponService` that enforces: 1) Length limits (3-50 characters) 2) Character validation (alphanumeric, hyphens, underscores only) 3) Uppercase normalisation. Both `findByCode()` and `validateByCode()` now sanitise input before database queries. Invalid format codes return null/invalid result early without hitting the database.

- [ ] **Validate billing address components** - `Order::create()` accepts `billing_address` array without validating structure. Malformed addresses could cause PDF generation issues or tax calculation failures.

- [ ] **Add CSRF protection to API billing endpoints** - Routes in `api.php` use `auth` middleware but not `verified` or CSRF tokens for state-changing operations.

---

## P2 - High Priority

### Data Integrity

- [ ] **Add database transactions to ReferralService::requestPayout()** - Currently uses transaction but doesn't lock commission rows, allowing potential race conditions if user submits multiple payout requests simultaneously.

- [ ] **Add optimistic locking to Subscription model** - Concurrent subscription updates (pause/cancel/renew) could result in inconsistent state. Add `version` column and check.

- [ ] **Handle partial payments in BTCPay** - BTCPay can receive partial payments but current flow only handles full settlement. Add `InvoicePartiallyPaid` webhook handling with admin notification.

### Missing Core Features

- [ ] **Implement provisioning API endpoints** - Routes commented out in `api.php`. Required for external integrations (WHMCS, custom portals). Create `ProductApiController` and `EntitlementApiController`.

- [ ] **Add subscription upgrade/downgrade via API** - `CommerceController::executeUpgrade()` referenced in routes but implementation needs review for proration handling.

- [ ] **Add payment method management UI tests** - `PaymentMethods` Livewire component exists but no feature tests for add/remove/set-default flows.

- [ ] **Implement credit note application to future invoices** - `CreditNote` model has `applied_to_order_id` but no service method to auto-apply credits to new orders.

### Error Handling

- [ ] **Add retry mechanism for failed invoice PDF generation** - `InvoiceService` doesn't handle DomPDF failures gracefully. Add queue job with retries.

- [ ] **Improve error messages for checkout failures** - Gateway errors are caught but user-facing messages are generic. Map common errors to helpful messages.

- [ ] **Add alerting for repeated payment failures** - DunningService logs failures but doesn't alert ops team. Add Slack/email notification after N failures.

### Testing Gaps

- [ ] **Add integration tests for Stripe webhook handlers** - `WebhookTest.php` exists but focuses on BTCPay. Add coverage for `StripeWebhookController` event handlers.

- [ ] **Add tests for concurrent subscription operations** - No tests for race conditions in pause/unpause/cancel/renew flows.

- [ ] **Add tests for multi-currency order flow** - `CurrencyServiceTest` tests conversion but not full checkout with display currency different from base.

- [ ] **Add tests for referral commission maturation edge cases** - What happens if order is refunded during maturation period?

---

## P3 - Medium Priority

### Performance

- [ ] **Add index on `orders.idempotency_key`** - Used in `CommerceService::createOrder()` lookup but not indexed. Add unique index.

- [ ] **Add index on `invoices.workspace_id, status`** - `DunningService` queries by workspace and status frequently.

- [ ] **Optimise subscription expiry query** - `SubscriptionService::processExpired()` loads all matching subscriptions. Use chunking for large datasets.

- [ ] **Cache exchange rates in-memory** - `ExchangeRate::convert()` hits DB on every call. Add short-lived cache.

- [ ] **Add eager loading to order/invoice queries** - Several Livewire components load orders without eager loading items/payments, causing N+1.

### Code Quality

- [ ] **Extract TaxResult to Data/ directory** - Currently embedded in `TaxService.php`. Move to `Data/TaxResult.php` for consistency with other DTOs.

- [ ] **Add return types to gateway contract methods** - `PaymentGatewayContract::refund()` returns array but should have a `RefundResult` DTO.

- [ ] **Consolidate order status transitions** - Status changes scattered across models and services. Create `OrderStateMachine` class.

- [ ] **Remove duplicate customer creation logic** - Both `CommerceService::ensureCustomer()` and gateway methods create customers. Consolidate.

- [ ] **Standardise money handling** - Mix of `float`, `decimal:2` casts, and `int` cents. Consider using `brick/money` package.

### DX Improvements

- [ ] **Add commerce:health Artisan command** - Check gateway connectivity, webhook configuration, pending dunning items.

- [ ] **Add commerce:simulate-webhook command** - For local testing of webhook handlers without real payments.

- [ ] **Document SKU format and lineage system** - Complex M1/M2/M3 hierarchy lacks examples. Add to CLAUDE.md or docs/.

- [ ] **Add typed properties to Livewire components** - Several use `public $variable` without types, causing IDE warnings.

### Observability

- [ ] **Add metrics for payment success/failure rates** - No Prometheus/StatsD integration for monitoring conversion rates.

- [ ] **Add structured logging to webhook handlers** - Current logs use ad-hoc format. Standardise with correlation IDs.

- [ ] **Add tracing spans for checkout flow** - No distributed tracing for debugging slow checkouts.

---

## P4 - Low Priority

### UI/UX

- [ ] **Add loading states to checkout Livewire components** - Button clicks don't show loading indicator during gateway calls.

- [ ] **Add subscription change confirmation modal** - `ChangePlan` component immediately processes; should confirm proration amount first.

- [ ] **Improve invoice PDF design** - Current template is basic. Add company branding, better line item formatting.

- [ ] **Add currency selector persistence** - `CurrencySelector` component sets session but doesn't persist to user preferences.

### Features (Nice to Have)

- [ ] **Add subscription pause scheduling** - Currently pause is immediate. Allow "pause starting next billing period".

- [ ] **Add invoice PDF caching** - Regenerates PDF on every download. Cache generated PDFs on disk.

- [ ] **Add webhook event viewer in admin** - `WebhookEvent` model exists but no admin UI to browse/retry events.

- [ ] **Add referral analytics dashboard** - Basic stats exist but no charts/trends visualization.

- [ ] **Support tax-inclusive pricing** - Config supports it but implementation assumes tax-exclusive.

### Technical Debt

- [ ] **Rename View/Modal/ to View/Livewire/** - Current naming is confusing (not all are modals).

- [ ] **Move factories to database/factories/** - Reference to `Database\Factories\OrderFactory` but factories may be missing.

- [ ] **Add strict types to all files** - Some files missing `declare(strict_types=1)`.

- [ ] **Update Carbon usage for v3 compatibility** - Some `diffInDays` calls may behave differently in Carbon 3.

---

## P5 - Nice to Have / Future

### Integrations

- [ ] **Add PayPal gateway** - For regions where BTCPay/Stripe aren't preferred.

- [ ] **Add accounting software export** - Xero/QuickBooks invoice sync.

- [ ] **Add email receipt provider integration** - Currently uses Laravel mail; consider dedicated receipt service.

### Advanced Features

- [ ] **Add subscription gifting** - Allow users to gift subscriptions to others.

- [ ] **Add group/team billing** - Multiple workspaces under one billing account.

- [ ] **Add usage alerts with thresholds** - Config has `usage_threshold_alerts` but no notification implementation.

- [ ] **Add dynamic pricing rules** - Volume discounts, time-based pricing changes.

---

## P6+ - Backlog / Ideas

- [ ] **Consider Paddle as alternative to Stripe** - For simplified EU VAT handling.
- [ ] **Research revenue recognition requirements** - ASC 606 compliance for enterprise customers.
- [ ] **Evaluate tax automation providers** - TaxJar, Avalara for complex tax scenarios.
- [ ] **Multi-entity billing consolidation** - Single invoice for M1 covering all M2/M3 transactions.

---

## Completed

### 2026-01-29 - Payment Security & Input Validation (P1-040, P1-041, P1-042)

- **Rate limit checkout session creation (P1-040)** - Verified `CheckoutRateLimiter` integration in `CommerceService::createCheckout()`. Rate limits of 5 attempts per 15-minute window protect against card testing attacks.

- **Add fraud scoring integration (P1-041)** - Integrated `FraudService` into checkout and webhook flows:
  - Pre-checkout: Velocity checks (IP/email order limits, failed payment tracking), geo-anomaly detection (country mismatch, high-risk countries)
  - Post-payment: Stripe Radar outcome processing via `charge.succeeded` and `payment_intent.succeeded` webhooks
  - Risk levels: normal, elevated (review), highest (block)
  - New `FraudBlockedException` for blocked orders
  - Fraud assessments stored in order/payment metadata for audit

- **Sanitise coupon codes (P1-042)** - Added `CouponService::sanitiseCode()` with:
  - Length limits: 3-50 characters
  - Character validation: alphanumeric, hyphens, underscores only
  - Uppercase normalisation
  - Early rejection of invalid formats before database queries

### 2026-01-29 - Webhook Security Fixes

- **Add rate limiting per IP for webhook endpoints (P2-075)** - Added `WebhookRateLimiter` service providing IP-based rate limiting for webhook endpoints:
  - Default: 60 requests/minute per IP, 300/minute for trusted gateway IPs
  - Per-gateway configurable limits via `config.php` (`commerce.webhooks.rate_limits`)
  - Trusted IP allowlist with CIDR range support (`commerce.webhooks.trusted_ips`)
  - Proper 429 responses with `Retry-After` and `X-RateLimit-*` headers
  - Replaces global `throttle:120,1` middleware with granular per-IP controls
  - Prevents rate limit exhaustion attacks against legitimate payment webhooks

- **Add idempotency handling for BTCPay/Stripe webhooks** - Added `isAlreadyProcessed()` check to both webhook controllers. Created `webhook_events` table with unique constraint on `(gateway, event_id)` for deduplication.

- **Add webhook replay protection window** - Webhook events stored permanently with status tracking. Processed/skipped events are rejected on subsequent attempts.

- **Add amount verification for BTCPay settlements** - New `verifyPaymentAmount()` method validates paid amount against order total. Underpayments rejected, overpayments logged.

- **Add currency mismatch detection** - Currency validation added to payment verification. Mismatched currencies result in order failure.

---

## Notes

- Priority levels: P1 (critical/security) through P6+ (backlog)
- Each item should be an isolated unit of work
- Security items should be addressed before public launch
- Tests should accompany all feature changes
