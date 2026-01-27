<?php

declare(strict_types=1);

namespace Core\Commerce\Services;

use Core\Commerce\Models\Entity;
use Core\Commerce\Models\Product;
use Core\Commerce\Models\ProductAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Product Catalog Service - Manages master catalog and entity assignments.
 *
 * M1 entities own products. M2/M3 entities access products via assignments.
 * Each assignment can have overrides for price, content, and visibility.
 */
class ProductCatalogService
{
    /**
     * Create a product (M1 only).
     *
     * @throws \InvalidArgumentException
     */
    public function createProduct(Entity $owner, array $data): Product
    {
        if (! $owner->isM1()) {
            throw new \InvalidArgumentException(
                "Only M1 (Master) entities can own products. Entity '{$owner->code}' is {$owner->type}."
            );
        }

        $data['owner_entity_id'] = $owner->id;
        $data['sku'] = strtoupper($data['sku'] ?? Product::generateSku($owner->code));

        return Product::create($data);
    }

    /**
     * Update a product.
     */
    public function updateProduct(Product $product, array $data): Product
    {
        // SKU is immutable after creation
        unset($data['sku'], $data['owner_entity_id']);

        $product->update($data);

        return $product->fresh();
    }

    /**
     * Delete a product (soft delete).
     */
    public function deleteProduct(Product $product): bool
    {
        return $product->delete();
    }

    /**
     * Assign a product to an M2/M3 entity.
     *
     * @throws \InvalidArgumentException
     */
    public function assignProduct(
        Entity $entity,
        Product $product,
        array $overrides = []
    ): ProductAssignment {
        if ($entity->isM1()) {
            throw new \InvalidArgumentException(
                'M1 entities own products directly. Use createProduct() instead.'
            );
        }

        // Check if assignment already exists
        $existing = ProductAssignment::where('entity_id', $entity->id)
            ->where('product_id', $product->id)
            ->first();

        if ($existing) {
            return $this->updateAssignment($existing, $overrides);
        }

        return ProductAssignment::create([
            'entity_id' => $entity->id,
            'product_id' => $product->id,
            ...$overrides,
        ]);
    }

    /**
     * Update an assignment's overrides.
     */
    public function updateAssignment(
        ProductAssignment $assignment,
        array $overrides
    ): ProductAssignment {
        $assignment->update($overrides);

        return $assignment->fresh();
    }

    /**
     * Remove a product assignment.
     */
    public function removeAssignment(ProductAssignment $assignment): bool
    {
        return $assignment->delete();
    }

    /**
     * Get all products for an entity.
     *
     * For M1: Returns owned products
     * For M2/M3: Returns assigned products
     */
    public function getProductsForEntity(
        Entity $entity,
        bool $activeOnly = true
    ): Collection {
        if ($entity->isM1()) {
            $query = Product::forOwner($entity->id);
            if ($activeOnly) {
                $query->active()->visible();
            }

            return $query->orderBy('sort_order')->get();
        }

        // M2/M3: Get assigned products
        $query = ProductAssignment::forEntity($entity->id)
            ->with('product');

        if ($activeOnly) {
            $query->active()->withActiveProducts();
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get a product for an entity with effective values.
     *
     * Returns array with effective price, name, description, etc.
     */
    public function getEffectiveProduct(Entity $entity, Product $product): array
    {
        if ($entity->isM1()) {
            return [
                'product' => $product,
                'assignment' => null,
                'sku' => $entity->buildSku($product->sku),
                'price' => $product->price,
                'name' => $product->name,
                'description' => $product->description,
                'image' => $product->image_url,
                'available_stock' => $product->stock_quantity,
            ];
        }

        $assignment = ProductAssignment::where('entity_id', $entity->id)
            ->where('product_id', $product->id)
            ->first();

        if (! $assignment) {
            return null;
        }

        return [
            'product' => $product,
            'assignment' => $assignment,
            'sku' => $assignment->getFullSku(),
            'price' => $assignment->getEffectivePrice(),
            'name' => $assignment->getEffectiveName(),
            'description' => $assignment->getEffectiveDescription(),
            'image' => $assignment->getEffectiveImage(),
            'available_stock' => $assignment->getAvailableStock(),
        ];
    }

    /**
     * Bulk assign products to an entity.
     */
    public function bulkAssign(
        Entity $entity,
        array $productIds,
        array $defaultOverrides = []
    ): int {
        $count = 0;

        DB::transaction(function () use ($entity, $productIds, $defaultOverrides, &$count) {
            foreach ($productIds as $productId) {
                $product = Product::find($productId);
                if ($product) {
                    $this->assignProduct($entity, $product, $defaultOverrides);
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Copy assignments from one entity to another.
     *
     * Useful for setting up new M3 entities with same products as parent M2.
     */
    public function copyAssignments(
        Entity $source,
        Entity $target,
        bool $includeOverrides = true
    ): int {
        $assignments = ProductAssignment::forEntity($source->id)->get();
        $count = 0;

        DB::transaction(function () use ($assignments, $target, $includeOverrides, &$count) {
            foreach ($assignments as $assignment) {
                $overrides = $includeOverrides ? [
                    'sku_suffix' => $assignment->sku_suffix,
                    'price_override' => $assignment->price_override,
                    'price_tier_overrides' => $assignment->price_tier_overrides,
                    'margin_percent' => $assignment->margin_percent,
                    'fixed_margin' => $assignment->fixed_margin,
                    'name_override' => $assignment->name_override,
                    'description_override' => $assignment->description_override,
                    'image_override' => $assignment->image_override,
                    'is_featured' => $assignment->is_featured,
                    'can_discount' => $assignment->can_discount,
                    'min_price' => $assignment->min_price,
                    'max_price' => $assignment->max_price,
                ] : [];

                $this->assignProduct($target, $assignment->product, $overrides);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Search products by name/SKU.
     */
    public function searchProducts(
        Entity $owner,
        string $query,
        int $limit = 20
    ): Collection {
        return Product::forOwner($owner->id)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%");
            })
            ->active()
            ->visible()
            ->limit($limit)
            ->get();
    }

    /**
     * Get products by category.
     */
    public function getByCategory(
        Entity $entity,
        string $category,
        bool $activeOnly = true
    ): Collection {
        if ($entity->isM1()) {
            $query = Product::forOwner($entity->id)
                ->inCategory($category);

            if ($activeOnly) {
                $query->active()->visible();
            }

            return $query->orderBy('sort_order')->get();
        }

        $query = ProductAssignment::forEntity($entity->id)
            ->with('product')
            ->whereHas('product', fn ($q) => $q->inCategory($category));

        if ($activeOnly) {
            $query->active()->withActiveProducts();
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Get featured products for an entity.
     */
    public function getFeaturedProducts(Entity $entity, int $limit = 10): Collection
    {
        if ($entity->isM1()) {
            return Product::forOwner($entity->id)
                ->active()
                ->visible()
                ->featured()
                ->limit($limit)
                ->get();
        }

        return ProductAssignment::forEntity($entity->id)
            ->with('product')
            ->active()
            ->featured()
            ->withActiveProducts()
            ->limit($limit)
            ->get();
    }

    /**
     * Get product statistics for an entity.
     */
    public function getProductStats(Entity $entity): array
    {
        if ($entity->isM1()) {
            $products = Product::forOwner($entity->id);

            return [
                'total' => $products->count(),
                'active' => $products->clone()->active()->count(),
                'featured' => $products->clone()->featured()->count(),
                'in_stock' => $products->clone()->inStock()->count(),
                'out_of_stock' => $products->clone()->where('stock_status', Product::STOCK_OUT)->count(),
                'low_stock' => $products->clone()->where('stock_status', Product::STOCK_LOW)->count(),
            ];
        }

        $assignments = ProductAssignment::forEntity($entity->id);

        return [
            'total' => $assignments->count(),
            'active' => $assignments->clone()->active()->count(),
            'featured' => $assignments->clone()->featured()->count(),
        ];
    }

    /**
     * Resolve SKU to product for an entity.
     *
     * Parses SKU lineage (M1-M2-SKU) and finds the corresponding product.
     */
    public function resolveSku(string $fullSku): ?array
    {
        // Parse SKU format: M1-M2-SKU or M1-M2-M3-SKU
        $parts = explode('-', $fullSku);

        if (count($parts) < 2) {
            return null;
        }

        // Last part is the base SKU
        $baseSku = array_pop($parts);

        // Find product by base SKU
        $product = Product::where('sku', $baseSku)->first();

        if (! $product) {
            // Try with combined last parts (SKU might have dashes)
            for ($i = count($parts) - 1; $i >= 1; $i--) {
                $testSku = implode('-', array_slice($parts, $i)).'-'.$baseSku;
                $product = Product::where('sku', $testSku)->first();
                if ($product) {
                    $parts = array_slice($parts, 0, $i);
                    break;
                }
            }
        }

        if (! $product) {
            return null;
        }

        // Build entity path from remaining parts
        $entityPath = implode('/', $parts);
        $entity = Entity::where('path', $entityPath)->first();

        if (! $entity) {
            return null;
        }

        return [
            'product' => $product,
            'entity' => $entity,
            'assignment' => $entity->isM1()
                ? null
                : ProductAssignment::where('entity_id', $entity->id)
                    ->where('product_id', $product->id)
                    ->first(),
        ];
    }
}
