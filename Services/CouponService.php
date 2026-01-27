<?php

namespace Core\Mod\Commerce\Services;

use Core\Tenant\Models\Package;
use Core\Tenant\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Core\Mod\Commerce\Contracts\Orderable;
use Core\Mod\Commerce\Data\CouponValidationResult;
use Core\Mod\Commerce\Models\Coupon;
use Core\Mod\Commerce\Models\CouponUsage;
use Core\Mod\Commerce\Models\Order;

/**
 * Coupon validation and application service.
 */
class CouponService
{
    /**
     * Find a coupon by code.
     */
    public function findByCode(string $code): ?Coupon
    {
        return Coupon::byCode($code)->first();
    }

    /**
     * Validate a coupon for a workspace and package.
     */
    public function validate(Coupon $coupon, Workspace $workspace, ?Package $package = null): CouponValidationResult
    {
        // Check if coupon is valid (active, within dates, not maxed out)
        if (! $coupon->isValid()) {
            return CouponValidationResult::invalid('This coupon is no longer valid');
        }

        // Check workspace usage limit
        if (! $coupon->canBeUsedByWorkspace($workspace->id)) {
            return CouponValidationResult::invalid('You have already used this coupon');
        }

        // Check if coupon applies to the package
        if ($package && ! $coupon->appliesToPackage($package->id)) {
            return CouponValidationResult::invalid('This coupon does not apply to the selected plan');
        }

        return CouponValidationResult::valid($coupon);
    }

    /**
     * Validate a coupon for any Orderable entity (User or Workspace).
     *
     * Returns boolean for use in CommerceService order creation.
     */
    public function validateForOrderable(Coupon $coupon, Orderable&Model $orderable, ?Package $package = null): bool
    {
        // Check if coupon is valid (active, within dates, not maxed out)
        if (! $coupon->isValid()) {
            return false;
        }

        // Check orderable usage limit
        if (! $coupon->canBeUsedByOrderable($orderable)) {
            return false;
        }

        // Check if coupon applies to the package
        if ($package && ! $coupon->appliesToPackage($package->id)) {
            return false;
        }

        return true;
    }

    /**
     * Validate a coupon by code.
     */
    public function validateByCode(string $code, Workspace $workspace, ?Package $package = null): CouponValidationResult
    {
        $coupon = $this->findByCode($code);

        if (! $coupon) {
            return CouponValidationResult::invalid('Invalid coupon code');
        }

        return $this->validate($coupon, $workspace, $package);
    }

    /**
     * Calculate discount for an amount.
     */
    public function calculateDiscount(Coupon $coupon, float $amount): float
    {
        return $coupon->calculateDiscount($amount);
    }

    /**
     * Record coupon usage after successful payment.
     */
    public function recordUsage(Coupon $coupon, Workspace $workspace, Order $order, float $discountAmount): CouponUsage
    {
        $usage = CouponUsage::create([
            'coupon_id' => $coupon->id,
            'workspace_id' => $workspace->id,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        // Increment global usage count
        $coupon->incrementUsage();

        return $usage;
    }

    /**
     * Record coupon usage for any Orderable entity.
     */
    public function recordUsageForOrderable(Coupon $coupon, Orderable&Model $orderable, Order $order, float $discountAmount): CouponUsage
    {
        $workspaceId = $orderable instanceof Workspace ? $orderable->id : null;

        $usage = CouponUsage::create([
            'coupon_id' => $coupon->id,
            'workspace_id' => $workspaceId,
            'order_id' => $order->id,
            'discount_amount' => $discountAmount,
        ]);

        // Increment global usage count
        $coupon->incrementUsage();

        return $usage;
    }

    /**
     * Get usage history for a coupon.
     */
    public function getUsageHistory(Coupon $coupon, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return $coupon->usages()
            ->with(['workspace', 'order'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get usage count for a workspace.
     */
    public function getWorkspaceUsageCount(Coupon $coupon, Workspace $workspace): int
    {
        return $coupon->usages()
            ->where('workspace_id', $workspace->id)
            ->count();
    }

    /**
     * Get total discount amount for a coupon.
     */
    public function getTotalDiscountAmount(Coupon $coupon): float
    {
        return $coupon->usages()->sum('discount_amount');
    }

    /**
     * Create a new coupon.
     */
    public function create(array $data): Coupon
    {
        // Normalise code to uppercase
        $data['code'] = strtoupper($data['code']);

        return Coupon::create($data);
    }

    /**
     * Deactivate a coupon.
     */
    public function deactivate(Coupon $coupon): void
    {
        $coupon->update(['is_active' => false]);
    }

    /**
     * Generate a random coupon code.
     */
    public function generateCode(int $length = 8): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Ensure uniqueness
        while (Coupon::where('code', $code)->exists()) {
            $code = $this->generateCode($length);
        }

        return $code;
    }

    /**
     * Generate multiple coupons with unique codes.
     *
     * @param  int  $count  Number of coupons to generate (1-100)
     * @param  array  $baseData  Base coupon data (shared settings for all coupons)
     * @return array<Coupon> Array of created coupons
     */
    public function generateBulk(int $count, array $baseData): array
    {
        $count = min(max($count, 1), 100);
        $coupons = [];
        $prefix = $baseData['code_prefix'] ?? '';
        unset($baseData['code_prefix']);

        for ($i = 0; $i < $count; $i++) {
            $code = $prefix ? $prefix.'-'.$this->generateCode(6) : $this->generateCode(8);
            $data = array_merge($baseData, ['code' => $code]);
            $coupons[] = $this->create($data);
        }

        return $coupons;
    }
}
