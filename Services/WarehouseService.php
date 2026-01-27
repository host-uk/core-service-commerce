<?php

declare(strict_types=1);

namespace Core\Commerce\Services;

use Core\Commerce\Models\Entity;
use Core\Commerce\Models\Inventory;
use Core\Commerce\Models\InventoryMovement;
use Core\Commerce\Models\Product;
use Core\Commerce\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Warehouse Service - Manages warehouses and inventory.
 *
 * Provides operations for:
 * - Warehouse management
 * - Stock tracking across warehouses
 * - Inventory movements and audit trail
 * - Stock allocation and fulfillment
 */
class WarehouseService
{
    /**
     * Create a warehouse for an M1 entity.
     */
    public function createWarehouse(Entity $entity, array $data): Warehouse
    {
        if (! $entity->isM1()) {
            throw new \InvalidArgumentException(
                'Only M1 (Master) entities can own warehouses.'
            );
        }

        $data['entity_id'] = $entity->id;
        $data['code'] = strtoupper($data['code']);

        return Warehouse::create($data);
    }

    /**
     * Get all warehouses for an entity.
     */
    public function getWarehousesForEntity(Entity $entity): Collection
    {
        return Warehouse::forEntity($entity->id)
            ->active()
            ->orderBy('is_primary', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get primary warehouse for an entity.
     */
    public function getPrimaryWarehouse(Entity $entity): ?Warehouse
    {
        return Warehouse::forEntity($entity->id)
            ->active()
            ->primary()
            ->first();
    }

    /**
     * Set a warehouse as primary.
     */
    public function setPrimaryWarehouse(Warehouse $warehouse): void
    {
        DB::transaction(function () use ($warehouse) {
            // Remove primary from all other warehouses for this entity
            Warehouse::forEntity($warehouse->entity_id)
                ->where('id', '!=', $warehouse->id)
                ->update(['is_primary' => false]);

            $warehouse->update(['is_primary' => true]);
        });
    }

    // Inventory operations

    /**
     * Get or create inventory record for product at warehouse.
     */
    public function getOrCreateInventory(Product $product, Warehouse $warehouse): Inventory
    {
        return Inventory::firstOrCreate(
            [
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
            [
                'quantity' => 0,
                'reserved_quantity' => 0,
                'incoming_quantity' => 0,
            ]
        );
    }

    /**
     * Add stock to a warehouse.
     */
    public function addStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $type = InventoryMovement::TYPE_PURCHASE,
        ?string $reference = null,
        ?string $notes = null,
        ?int $unitCost = null
    ): Inventory {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        DB::transaction(function () use ($inventory, $quantity, $type, $reference, $notes, $unitCost) {
            $inventory->addStock($quantity);

            if ($unitCost !== null) {
                $inventory->update(['unit_cost' => $unitCost]);
            }

            InventoryMovement::record(
                $inventory,
                $type,
                $quantity,
                $reference,
                $notes,
                null,
                $unitCost
            );
        });

        return $inventory->fresh();
    }

    /**
     * Remove stock from a warehouse.
     */
    public function removeStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $type = InventoryMovement::TYPE_SALE,
        ?string $reference = null,
        ?string $notes = null
    ): bool {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        if ($inventory->getAvailableQuantity() < $quantity) {
            return false;
        }

        DB::transaction(function () use ($inventory, $quantity, $type, $reference, $notes) {
            $inventory->removeStock($quantity);

            InventoryMovement::record(
                $inventory,
                $type,
                -$quantity,
                $reference,
                $notes
            );
        });

        return true;
    }

    /**
     * Reserve stock for an order.
     */
    public function reserveStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $orderId
    ): bool {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        if (! $inventory->reserve($quantity)) {
            return false;
        }

        InventoryMovement::record(
            $inventory,
            InventoryMovement::TYPE_RESERVED,
            -$quantity,
            $orderId,
            "Reserved for order {$orderId}"
        );

        return true;
    }

    /**
     * Release reserved stock.
     */
    public function releaseStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $orderId
    ): void {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        $inventory->release($quantity);

        InventoryMovement::record(
            $inventory,
            InventoryMovement::TYPE_RELEASED,
            $quantity,
            $orderId,
            "Released from order {$orderId}"
        );
    }

    /**
     * Fulfill reserved stock (convert to sale).
     */
    public function fulfillStock(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        string $orderId
    ): bool {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        if (! $inventory->fulfill($quantity)) {
            return false;
        }

        InventoryMovement::record(
            $inventory,
            InventoryMovement::TYPE_SALE,
            -$quantity,
            $orderId,
            "Fulfilled for order {$orderId}"
        );

        return true;
    }

    /**
     * Transfer stock between warehouses.
     */
    public function transferStock(
        Product $product,
        Warehouse $from,
        Warehouse $to,
        int $quantity,
        ?string $notes = null
    ): bool {
        $fromInventory = $this->getOrCreateInventory($product, $from);

        if ($fromInventory->getAvailableQuantity() < $quantity) {
            return false;
        }

        $toInventory = $this->getOrCreateInventory($product, $to);
        $reference = 'TRANSFER-'.now()->format('YmdHis');

        DB::transaction(function () use ($fromInventory, $toInventory, $quantity, $reference, $notes) {
            $fromInventory->removeStock($quantity);
            $toInventory->addStock($quantity);

            InventoryMovement::record(
                $fromInventory,
                InventoryMovement::TYPE_TRANSFER_OUT,
                -$quantity,
                $reference,
                $notes
            );

            InventoryMovement::record(
                $toInventory,
                InventoryMovement::TYPE_TRANSFER_IN,
                $quantity,
                $reference,
                $notes
            );
        });

        return true;
    }

    /**
     * Perform stock count adjustment.
     */
    public function adjustStock(
        Product $product,
        Warehouse $warehouse,
        int $newQuantity,
        ?string $notes = null
    ): int {
        $inventory = $this->getOrCreateInventory($product, $warehouse);

        $difference = DB::transaction(function () use ($inventory, $newQuantity, $notes) {
            $difference = $inventory->setCount($newQuantity);

            InventoryMovement::record(
                $inventory,
                InventoryMovement::TYPE_COUNT,
                $difference,
                'COUNT-'.now()->format('YmdHis'),
                $notes ?? 'Physical count adjustment'
            );

            return $difference;
        });

        return $difference;
    }

    // Query methods

    /**
     * Get total stock across all warehouses.
     */
    public function getTotalStock(Product $product): int
    {
        return (int) Inventory::forProduct($product->id)->sum('quantity');
    }

    /**
     * Get available stock across all warehouses.
     */
    public function getTotalAvailableStock(Product $product): int
    {
        return (int) (Inventory::forProduct($product->id)
            ->selectRaw('SUM(quantity - reserved_quantity) as available')
            ->value('available') ?? 0);
    }

    /**
     * Get stock by warehouse.
     */
    public function getStockByWarehouse(Product $product): Collection
    {
        return Inventory::forProduct($product->id)
            ->with('warehouse')
            ->get();
    }

    /**
     * Find best warehouse to fulfill order.
     */
    public function findBestWarehouse(Product $product, int $quantity): ?Warehouse
    {
        return Warehouse::active()
            ->canShip()
            ->whereHas('inventory', function ($query) use ($product, $quantity) {
                $query->forProduct($product->id)
                    ->whereRaw('(quantity - reserved_quantity) >= ?', [$quantity]);
            })
            ->orderBy('is_primary', 'desc')
            ->first();
    }

    /**
     * Get low stock products.
     */
    public function getLowStockProducts(Entity $entity): Collection
    {
        return Inventory::with(['product', 'warehouse'])
            ->whereHas('warehouse', fn ($q) => $q->forEntity($entity->id))
            ->lowStock()
            ->get();
    }

    /**
     * Get out of stock products.
     */
    public function getOutOfStockProducts(Entity $entity): Collection
    {
        return Inventory::with(['product', 'warehouse'])
            ->whereHas('warehouse', fn ($q) => $q->forEntity($entity->id))
            ->outOfStock()
            ->get();
    }

    /**
     * Get inventory movements for a product.
     */
    public function getMovementHistory(Product $product, int $limit = 50): Collection
    {
        return InventoryMovement::forProduct($product->id)
            ->with(['warehouse', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
