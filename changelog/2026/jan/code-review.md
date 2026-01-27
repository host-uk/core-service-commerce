# Commerce Module Review

**Updated:** 2026-01-21 - Rate limiting, idempotency keys, and expired order cleanup implemented

## Overview

The Commerce module is a comprehensive billing and subscription management system that handles:
- Order creation and checkout flows (single purchases and subscriptions)
- Multi-gateway payment processing (BTCPay primary, Stripe secondary)
- Subscription lifecycle management (create, pause, cancel, renew)
- Dunning system for failed payment recovery (retry, pause, suspend, cancel stages)
- Invoice generation and PDF creation
- Coupon/discount management
- Tax calculation (UK VAT, EU OSS, US state taxes, Australian GST)
- Refund processing
- Permission matrix for multi-entity hierarchies (M1/M2/M3 model)
- Webhook handling with deduplication and audit logging

The module follows the modular monolith architecture with Boot.php registering services, routes, commands, and Livewire components.

## Production Readiness Score: 94/100 (was 92/100 - checkout protection and cleanup added 2026-01-21)

The module is well-architected with solid fundamentals. All critical issues fixed in Wave 4.

## Critical Issues (Must Fix)

- [x] **Webhook signature verification bypassed in tests** - VERIFIED: Production implementation properly calls `$this->gateway->verifyWebhookSignature()` and returns 401 on failure. Already secure.

- [x] **Missing database indexes on webhook_events table** - VERIFIED: Migration already has `$table->unique(['gateway', 'event_id'])` index. Already correct.

- [x] **Order.workspace scope uses wrong field** - FIXED: `scopeForWorkspace` now uses `orderable_type` and `orderable_id` for polymorphic relations.

- [x] **Invoice workspace relationship missing for User orderables** - FIXED: Added `getWorkspaceIdAttribute()` accessor and `getResolvedWorkspace()` method to Order model. Webhook controllers updated to use these.

- [x] **Missing transaction isolation in webhook handlers** - FIXED: Both `BTCPayWebhookController` and `StripeWebhookController` now wrap processing in `DB::transaction()`.

- [x] **BTCPay gateway chargePaymentMethod** - FIXED: `CommerceService::retryInvoicePayment()` now correctly checks `$payment->status === 'succeeded'` and handles BTCPay pending payments appropriately.

## Recommended Improvements

- [x] **Add rate limiting per customer on checkout** - DONE: `CheckoutRateLimiter` service implemented with sliding window rate limiting. Uses workspace/user/IP hierarchy for throttle keys. 5 attempts per 15 minutes.

- [x] **Add idempotency keys to order creation** - DONE: `idempotency_key` field added to orders table. Migration `2026_01_21_120000_add_idempotency_key_to_orders_table.php` created. Used in CommerceService and CheckoutPage.

- [x] **Extract CouponValidationResult to dedicated file** - DONE: Extracted to `Data/CouponValidationResult.php`.

- [x] **Add scheduled job for expired order cleanup** - DONE: `CleanupExpiredOrders` command (`commerce:cleanup-orders`) created. Cancels pending orders older than configurable TTL. Supports `--dry-run` and `--ttl` options. Chunked processing with logging.

- [ ] **Add observability/metrics** - Consider adding metrics for: payment success rate, dunning recovery rate, webhook processing time, gateway errors.

- [ ] **Standardise error responses in API controllers** - `CommerceController` methods should return consistent JSON error structures.

- [x] **Add model factories for Commerce models** - DONE: Created OrderFactory, InvoiceFactory, SubscriptionFactory, CouponFactory, and PaymentFactory in `Database/Factories/`.

- [ ] **Implement webhook retry mechanism** - Failed webhooks (logged with status `failed`) have no automatic retry. Consider dead-letter queue or scheduled retry.

- [ ] **Add cancellation feedback collection** - Store cancellation reasons/feedback when users cancel subscriptions for product insights.

- [ ] **Tax ID validation is config-gated but implementation unclear** - Config has `validate_tax_ids_api` but actual HMRC/VIES API integration not visible in TaxService.

## Missing Features (Future)

- [ ] **Usage-based billing** - Config has `features.usage_billing => false` as placeholder for metered billing.

- [ ] **Multi-currency support** - Currently defaults to GBP. Config exists but full multi-currency handling not implemented.

- [ ] **Credit notes** - No model for credit notes when issuing partial refunds or adjustments.

- [ ] **Payment method management UI** - `PaymentMethods` Livewire component exists but no visible add/remove card flow.

- [ ] **Subscription upgrade preview API** - `previewUpgrade` endpoint exists but no corresponding frontend to show prorated amounts.

- [ ] **Bulk coupon generation** - CouponService can generate codes but no bulk creation for marketing campaigns.

- [ ] **Affiliate/referral tracking** - `RewardAgentReferralOnSubscription` listener exists but full referral system incomplete.

- [ ] **Warehouse/inventory system** - Models exist (`Warehouse`, `Inventory`, `InventoryMovement`) but services not implemented. May be for physical goods expansion.

## Test Coverage Assessment

**Current Coverage:**
- `CheckoutFlowTest.php` - Basic checkout flow testing
- `CompoundSkuTest.php` - SKU parsing and building
- `ContentOverrideServiceTest.php` - Content override system
- `CouponServiceTest.php` - Comprehensive coupon validation and calculation
- `DunningServiceTest.php` - Thorough dunning lifecycle testing
- `ProcessSubscriptionRenewalTest.php` - Renewal job testing
- `RefundServiceTest.php` - Refund flow with mocked gateway
- `SubscriptionServiceTest.php` - Subscription CRUD operations
- `TaxServiceTest.php` - Tax calculation scenarios
- `WebhookTest.php` - Extensive webhook handling for both gateways

**Coverage Gaps:**
- [ ] No unit tests (Unit/ directory empty)
- [ ] No tests for `PermissionMatrixService` despite complex hierarchy logic
- [ ] No tests for `InvoiceService` PDF generation
- [ ] No tests for `ProductCatalogService` and `WarehouseService`
- [ ] No integration tests for actual gateway API calls
- [ ] No tests for admin Livewire components
- [ ] No tests for web checkout Livewire components
- [ ] Missing edge case tests for concurrent webhook delivery
- [ ] No load/stress testing for high-volume scenarios

**Test Quality:**
- Tests use Pest with good describe/it structure
- Proper use of RefreshDatabase trait
- Good mocking of external dependencies
- Notification and Event faking used appropriately

## Security Concerns

1. **Webhook signature verification** - Properly implemented using HMAC-SHA256 for BTCPay and Stripe's built-in verification. Signatures are masked in logs.

2. **No explicit input validation on order metadata** - The `metadata` array passed to orders is stored directly. Consider schema validation to prevent injection of malicious data.

3. **Invoice number generation uses sequential IDs** - Pattern `INV-{year}-{sequence}` is predictable. Not a vulnerability but consider if invoice numbers should be harder to enumerate.

4. **PDF generation with user data** - Invoice PDFs include user-provided billing names/addresses. Ensure DomPDF or Snappy has XSS protections enabled.

5. **Coupon code brute-forcing** - No rate limiting on coupon validation. Attackers could enumerate valid codes.

6. **Permission matrix training mode** - `training_mode` config allows undefined permissions to prompt for approval. Ensure this is NEVER enabled in production (`COMMERCE_MATRIX_TRAINING=false`).

7. **API authentication** - Provisioning API uses `commerce.api` middleware but implementation not visible. Verify bearer token validation is secure.

8. **No audit logging for admin actions** - Coupon creation, refund processing, subscription cancellation by admins should be logged for compliance.

## Notes

### Architecture Observations
- Clean separation between gateway-specific logic (PaymentGatewayContract implementations) and business logic (Services)
- Good use of Laravel events for subscription lifecycle (SubscriptionCreated, SubscriptionUpdated, etc.)
- Dunning service follows best practices with configurable retry intervals and grace periods
- Webhook logging with deduplication prevents replay attacks and aids debugging

### Configuration
- Comprehensive config file covering all aspects of billing
- Environment variable support for sensitive values (API keys, secrets)
- Feature flags allow gradual rollout of capabilities

### Code Quality
- Consistent use of type hints and return types
- Proper use of database transactions for multi-step operations
- Good error handling in critical paths
- UK English spelling used consistently (colour, organisation, centre)

### Dependencies
- `barryvdh/laravel-dompdf` for PDF generation
- Stripe PHP SDK (implied by StripeGateway)
- BTCPay Server API client (custom implementation)

### Migration State
- 11 migrations covering all models
- Latest migrations dated 2026-01-21 (pause_count and webhook_events)
- No down() methods visible - verify rollback capability
