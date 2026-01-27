<?php

declare(strict_types=1);

namespace Core\Service\Commerce;

use Core\Commerce\Models\Order;
use Core\Events\AdminPanelBooting;
use Core\Front\Admin\AdminMenuRegistry;
use Core\Service\Contracts\ServiceDefinition;
use Core\Service\ServiceVersion;
use Illuminate\Support\ServiceProvider;

/**
 * Commerce Service
 *
 * Orders and subscriptions service layer.
 * Uses Core\Commerce as the engine.
 */
class Boot extends ServiceProvider implements ServiceDefinition
{
    /**
     * Events this service listens to.
     *
     * @var array<class-string, string>
     */
    public static array $listens = [
        AdminPanelBooting::class => 'onAdminPanel',
    ];

    /**
     * Bootstrap the service.
     */
    public function boot(): void
    {
        app(AdminMenuRegistry::class)->register($this);
    }

    /**
     * Get the service definition for seeding platform_services.
     */
    public static function definition(): array
    {
        return [
            'code' => 'commerce',
            'module' => 'Commerce',
            'name' => 'Commerce',
            'tagline' => 'Orders and subscriptions',
            'description' => 'Manage orders, subscriptions, and billing for your digital products.',
            'icon' => 'shopping-cart',
            'color' => 'green',
            'entitlement_code' => 'core.srv.commerce',
            'sort_order' => 70,
        ];
    }

    /**
     * Admin menu items for this service.
     */
    public function adminMenuItems(): array
    {
        $isServices = request()->routeIs('hub.services') && request()->route('service') === 'commerce';

        return [
            [
                'group' => 'services',
                'service' => 'commerce',
                'priority' => 70,
                'entitlement' => 'core.srv.commerce',
                'item' => fn () => [
                    'label' => 'Commerce',
                    'icon' => 'shopping-cart',
                    'color' => 'green',
                    'href' => route('hub.services', ['service' => 'commerce']),
                    'active' => $isServices,
                    'children' => [
                        ['label' => 'Dashboard', 'icon' => 'gauge', 'href' => route('hub.services', ['service' => 'commerce']), 'active' => $isServices && in_array(request()->route('tab'), [null, 'dashboard'])],
                        ['label' => 'Orders', 'icon' => 'receipt', 'href' => route('hub.services', ['service' => 'commerce', 'tab' => 'orders']), 'active' => $isServices && request()->route('tab') === 'orders', 'badge' => $this->pendingOrders()],
                        ['label' => 'Subscriptions', 'icon' => 'rotate', 'href' => route('hub.services', ['service' => 'commerce', 'tab' => 'subscriptions']), 'active' => $isServices && request()->route('tab') === 'subscriptions'],
                        ['label' => 'Coupons', 'icon' => 'ticket', 'href' => route('hub.services', ['service' => 'commerce', 'tab' => 'coupons']), 'active' => $isServices && request()->route('tab') === 'coupons'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get pending orders count.
     */
    protected function pendingOrders(): ?int
    {
        $count = Order::whereIn('status', ['pending', 'processing'])->count();

        return $count ?: null;
    }

    /**
     * Register admin panel components.
     */
    public function onAdminPanel(AdminPanelBooting $event): void
    {
        // Service-specific admin routes could go here
        // Components are registered by Core\Commerce
    }

    public function menuPermissions(): array
    {
        return [];
    }

    public function canViewMenu(?object $user, ?object $workspace): bool
    {
        return $user !== null;
    }

    public static function version(): ServiceVersion
    {
        return new ServiceVersion(1, 0, 0);
    }

    /**
     * Service dependencies.
     */
    public static function dependencies(): array
    {
        return [];
    }
}
