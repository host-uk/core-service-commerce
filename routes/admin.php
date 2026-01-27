<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Hub Routes (Billing & Admin)
|--------------------------------------------------------------------------
*/

// Billing (user-facing hub pages)
Route::prefix('hub/billing')->name('hub.billing.')->group(function () {
    Route::get('/', \Core\Commerce\View\Modal\Web\Dashboard::class)->name('index');
    Route::get('/invoices', \Core\Commerce\View\Modal\Web\Invoices::class)->name('invoices');
    Route::get('/invoices/{invoice}/pdf', [\Core\Commerce\Controllers\InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/invoices/{invoice}/view', [\Core\Commerce\Controllers\InvoiceController::class, 'view'])->name('invoices.view');
    Route::get('/payment-methods', \Core\Commerce\View\Modal\Web\PaymentMethods::class)->name('payment-methods');
    Route::get('/subscription', \Core\Commerce\View\Modal\Web\Subscription::class)->name('subscription');
    Route::get('/change-plan', \Core\Commerce\View\Modal\Web\ChangePlan::class)->name('change-plan');
    Route::get('/affiliates', \Core\Commerce\View\Modal\Web\ReferralDashboard::class)->name('affiliates');
});

// Commerce management (admin only - Hades tier)
Route::prefix('hub/commerce')->name('hub.commerce.')->group(function () {
    Route::get('/', \Core\Commerce\View\Modal\Admin\Dashboard::class)->name('dashboard');
    Route::get('/orders', \Core\Commerce\View\Modal\Admin\OrderManager::class)->name('orders');
    Route::get('/subscriptions', \Core\Commerce\View\Modal\Admin\SubscriptionManager::class)->name('subscriptions');
    Route::get('/coupons', \Core\Commerce\View\Modal\Admin\CouponManager::class)->name('coupons');
    Route::get('/entities', \Core\Commerce\View\Modal\Admin\EntityManager::class)->name('entities');
    Route::get('/permissions', \Core\Commerce\View\Modal\Admin\PermissionMatrixManager::class)->name('permissions');
    Route::get('/products', \Core\Commerce\View\Modal\Admin\ProductManager::class)->name('products');
    Route::get('/credit-notes', \Core\Commerce\View\Modal\Admin\CreditNoteManager::class)->name('credit-notes');
    Route::get('/referrals', \Core\Commerce\View\Modal\Admin\ReferralManager::class)->name('referrals');
});
