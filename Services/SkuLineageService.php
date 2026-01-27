<?php

declare(strict_types=1);

namespace Core\Commerce\Services;

use Core\Commerce\Models\Entity;
use Core\Commerce\Models\Product;
use Core\Commerce\Models\ProductAssignment;
use Illuminate\Support\Collection;

/**
 * SKU Lineage Service - Tracks product identity across entity hierarchy.
 *
 * SKU Format: M1-M2-M3-BASEKU
 * Example: ORGORG-WBUTS-WB500L (Original Organics → Waterbutts → 500L Butt)
 *
 * The SKU lineage allows:
 * - Tracing any sale back to its origin
 * - Understanding which entity sold what
 * - Reporting across the entire hierarchy
 */
class SkuLineageService
{
    /**
     * Build full SKU for a product at a specific entity.
     *
     * @param  Product  $product  The base product
     * @param  Entity  $entity  The selling entity
     * @param  string|null  $suffix  Optional entity-specific suffix
     */
    public function buildSku(Product $product, Entity $entity, ?string $suffix = null): string
    {
        $baseSku = $suffix ?? $product->sku;

        return $entity->buildSku($baseSku);
    }

    /**
     * Build SKU from assignment (uses assignment's suffix if set).
     */
    public function buildFromAssignment(ProductAssignment $assignment): string
    {
        return $assignment->getFullSku();
    }

    /**
     * Parse a full SKU into its components.
     *
     * @return array{entity_codes: array, base_sku: string, full_path: string}
     */
    public function parseSku(string $fullSku): array
    {
        $parts = explode('-', strtoupper($fullSku));

        if (count($parts) < 2) {
            return [
                'entity_codes' => [],
                'base_sku' => $fullSku,
                'full_path' => '',
            ];
        }

        // Base SKU is the last part
        $baseSku = array_pop($parts);

        return [
            'entity_codes' => $parts,
            'base_sku' => $baseSku,
            'full_path' => implode('/', $parts),
        ];
    }

    /**
     * Resolve SKU to product and entity.
     *
     * @return array{product: Product, entity: Entity, assignment: ?ProductAssignment}|null
     */
    public function resolve(string $fullSku): ?array
    {
        $parsed = $this->parseSku($fullSku);

        if (empty($parsed['entity_codes'])) {
            return null;
        }

        // Try to find the entity by path
        $entity = Entity::where('path', $parsed['full_path'])->first();

        if (! $entity) {
            return null;
        }

        // Find product - might need to try multiple combinations
        $product = $this->findProduct($parsed['base_sku'], $parsed['entity_codes']);

        if (! $product) {
            return null;
        }

        $assignment = null;
        if (! $entity->isM1()) {
            $assignment = ProductAssignment::where('entity_id', $entity->id)
                ->where('product_id', $product->id)
                ->first();
        }

        return [
            'product' => $product,
            'entity' => $entity,
            'assignment' => $assignment,
        ];
    }

    /**
     * Find product by base SKU, trying various combinations.
     */
    protected function findProduct(string $baseSku, array $entityCodes): ?Product
    {
        // Direct match first
        $product = Product::where('sku', $baseSku)->first();
        if ($product) {
            return $product;
        }

        // Product SKU might include entity prefix already
        // Try progressively longer SKUs
        $testSku = $baseSku;
        for ($i = count($entityCodes) - 1; $i >= 0; $i--) {
            $testSku = $entityCodes[$i].'-'.$testSku;
            $product = Product::where('sku', $testSku)->first();
            if ($product) {
                return $product;
            }
        }

        return null;
    }

    /**
     * Get all SKU variants for a product across entities.
     *
     * Returns all the different SKUs under which this product is sold.
     */
    public function getSkuVariants(Product $product): Collection
    {
        $variants = collect();

        // Owner's SKU
        $owner = $product->ownerEntity;
        if ($owner) {
            $variants->push([
                'entity' => $owner,
                'sku' => $owner->buildSku($product->sku),
                'type' => 'owner',
            ]);
        }

        // Assignment SKUs
        $assignments = $product->assignments()->with('entity')->get();
        foreach ($assignments as $assignment) {
            $variants->push([
                'entity' => $assignment->entity,
                'sku' => $assignment->getFullSku(),
                'type' => 'assignment',
                'has_suffix' => ! is_null($assignment->sku_suffix),
            ]);
        }

        return $variants;
    }

    /**
     * Validate SKU format.
     */
    public function validateSku(string $sku): array
    {
        $errors = [];

        // Check length
        if (strlen($sku) > 64) {
            $errors[] = 'SKU exceeds maximum length of 64 characters.';
        }

        // Check for valid characters
        if (! preg_match('/^[A-Z0-9\-]+$/', strtoupper($sku))) {
            $errors[] = 'SKU contains invalid characters. Only A-Z, 0-9, and hyphens allowed.';
        }

        // Check minimum parts
        $parts = explode('-', $sku);
        if (count($parts) < 2) {
            $errors[] = 'SKU must contain at least one entity code and a base SKU.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Generate a unique base SKU.
     */
    public function generateBaseSku(string $prefix = '', int $length = 8): string
    {
        return Product::generateSku($prefix);
    }

    /**
     * Check if a base SKU is available.
     */
    public function isSkuAvailable(string $baseSku): bool
    {
        return ! Product::where('sku', strtoupper($baseSku))->exists();
    }

    /**
     * Trace SKU lineage - get full chain of entities.
     */
    public function traceLineage(string $fullSku): array
    {
        $parsed = $this->parseSku($fullSku);

        if (empty($parsed['entity_codes'])) {
            return [];
        }

        $chain = [];
        $currentPath = '';

        foreach ($parsed['entity_codes'] as $code) {
            $currentPath .= ($currentPath ? '/' : '').$code;
            $entity = Entity::where('path', $currentPath)->first();

            if ($entity) {
                $chain[] = [
                    'code' => $code,
                    'entity' => $entity,
                    'type' => $entity->type,
                    'name' => $entity->name,
                ];
            }
        }

        return $chain;
    }

    /**
     * Get reporting data for a SKU (useful for analytics).
     */
    public function getSkuReport(string $fullSku): ?array
    {
        $resolved = $this->resolve($fullSku);

        if (! $resolved) {
            return null;
        }

        $lineage = $this->traceLineage($fullSku);
        $m1 = collect($lineage)->first(fn ($e) => $e['type'] === 'M1');

        return [
            'full_sku' => strtoupper($fullSku),
            'base_sku' => $resolved['product']->sku,
            'product_name' => $resolved['product']->name,
            'selling_entity' => [
                'id' => $resolved['entity']->id,
                'code' => $resolved['entity']->code,
                'name' => $resolved['entity']->name,
                'type' => $resolved['entity']->type,
            ],
            'owner_entity' => $m1 ? [
                'id' => $m1['entity']->id,
                'code' => $m1['code'],
                'name' => $m1['name'],
            ] : null,
            'lineage' => $lineage,
            'effective_price' => $resolved['assignment']
                ? $resolved['assignment']->getEffectivePrice()
                : $resolved['product']->price,
        ];
    }
}
