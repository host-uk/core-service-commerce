# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Is

`host-uk/core-commerce` - A Laravel package providing orders, subscriptions, invoices, and payment processing. Namespace: `Core\Mod\Commerce`.

## Commands

```bash
composer run lint          # vendor/bin/pint
composer run test          # vendor/bin/pest
vendor/bin/pint --dirty    # Format changed files only
vendor/bin/pest --filter=CheckoutFlowTest  # Run single test file
```

## Architecture

### Boot & Event System

This is a **Laravel package** (not a standalone app). `Boot.php` extends `ServiceProvider` and uses the Core Framework's event-driven lazy-loading:

```php
public static array $listens = [
    AdminPanelBooting::class => 'onAdminPanel',
    ApiRoutesRegistering::class => 'onApiRoutes',
    WebRoutesRegistering::class => 'onWebRoutes',
    ConsoleBooting::class => 'onConsole',
];
```

### Service Layer

Business logic lives in `Services/`. All services are registered as singletons in `Boot::register()`:

- **CommerceService** - Order orchestration
- **SubscriptionService** - Subscription lifecycle
- **InvoiceService** - Invoice generation
- **TaxService** - Jurisdiction-based tax calculation
- **CouponService** - Discount validation and application
- **CurrencyService** - Multi-currency support with exchange rates
- **DunningService** - Failed payment retry logic
- **UsageBillingService** - Metered/usage-based billing
- **ReferralService** - Affiliate tracking and commissions
- **PaymentMethodService** - Stored payment methods

### Payment Gateways

Pluggable via `PaymentGatewayContract`:
- `StripeGateway` - Primary (SaaS)
- `BTCPayGateway` - Cryptocurrency

### Domain Events

Events in `Events/` trigger listeners for loose coupling:
- `OrderPaid` - Payment succeeded
- `SubscriptionCreated`, `SubscriptionRenewed`, `SubscriptionUpdated`, `SubscriptionCancelled`

### Livewire Components

Located in `View/Modal/` (not `Livewire/`):
- `View/Modal/Web/` - User-facing (checkout, invoices, subscription management)
- `View/Modal/Admin/` - Admin panel (managers for orders, coupons, products, etc.)

## Key Directories

```
Boot.php              # ServiceProvider, event registration
config.php            # Currencies, gateways, tax rules
Models/               # Eloquent models (Order, Subscription, Invoice, etc.)
Services/             # Business logic layer
View/Modal/           # Livewire components
Routes/               # web.php, api.php, admin.php, console.php
Migrations/           # Database schema
Events/               # Domain events
Listeners/            # Event subscribers
tests/Feature/        # Pest feature tests
```

## Conventions

- **UK English** - colour, organisation, centre
- **PSR-12** via Laravel Pint
- **Pest** for testing (not PHPUnit syntax)
- **Strict types** - `declare(strict_types=1);` in all files
- **Livewire + Flux Pro** for UI components
- **Font Awesome Pro** for icons (not Heroicons)

## Namespaces

```php
use Core\Mod\Commerce\Models\Order;
use Core\Mod\Commerce\Services\SubscriptionService;
use Core\Service\Commerce\SomeUtility;  // Alternative service namespace
```

## Testing

Tests are in `tests/` with Pest:

```php
test('user can checkout', function () {
    // ...
});
```

Run a specific test:
```bash
vendor/bin/pest --filter="checkout"
vendor/bin/pest tests/Feature/CheckoutFlowTest.php
```