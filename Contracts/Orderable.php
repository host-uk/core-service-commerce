<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Contracts;

/**
 * Contract for entities that can place orders.
 *
 * Implemented by User, Workspace, or any entity that needs billing.
 */
interface Orderable
{
    /**
     * Get the billing name for orders.
     */
    public function getBillingName(): ?string;

    /**
     * Get the billing email for orders.
     */
    public function getBillingEmail(): string;

    /**
     * Get the billing address for orders.
     */
    public function getBillingAddress(): ?array;

    /**
     * Get the tax country code (for tax calculation).
     */
    public function getTaxCountry(): ?string;
}
