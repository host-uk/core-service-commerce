<?php

declare(strict_types=1);

namespace Core\Mod\Commerce\Services;

use Core\Mod\Commerce\Data\BundleItem;
use Core\Mod\Commerce\Data\ParsedItem;
use Core\Mod\Commerce\Data\SkuOption;
use Core\Mod\Commerce\Data\SkuParseResult;

/**
 * Parse compound SKU strings into structured data.
 *
 * Format: SKU-<opt>~<val>*<qty>[-<opt>~<val>*<qty>]...
 *
 * Separators:
 *   -    Option separator (within an item)
 *   ~    Value indicator (option~value)
 *   *    Quantity indicator (optional, default 1)
 *   ,    Item separator (multiple items)
 *   |    Bundle separator (grouped items for discount)
 *
 * Examples:
 *   LAPTOP-ram~16gb-ssd~512gb           Single item with options
 *   LAPTOP-ram~16gb-cover~black*2       Item with quantity on option
 *   LAPTOP-ram~16gb,MOUSE,PAD           Multiple separate items
 *   LAPTOP-ram~16gb|MOUSE|PAD           Bundle (discount lookup)
 *
 * One scan tells you everything. No lookups. No mistakes.
 */
class SkuParserService
{
    /**
     * Parse a compound SKU string into structured data.
     */
    public function parse(string $compoundSku): SkuParseResult
    {
        $compoundSku = trim($compoundSku);

        if ($compoundSku === '') {
            return new SkuParseResult([]);
        }

        // Split by comma for multiple items
        $segments = explode(',', $compoundSku);
        $parsedItems = [];

        foreach ($segments as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            // Check for bundle separator
            if (str_contains($segment, '|')) {
                $bundleParts = explode('|', $segment);
                $bundleItems = [];

                foreach ($bundleParts as $part) {
                    $part = trim($part);
                    if ($part !== '') {
                        $bundleItems[] = $this->parseItem($part);
                    }
                }

                if (! empty($bundleItems)) {
                    $parsedItems[] = new BundleItem(
                        items: $bundleItems,
                        hash: $this->hashBundle($bundleItems)
                    );
                }
            } else {
                $parsedItems[] = $this->parseItem($segment);
            }
        }

        return new SkuParseResult($parsedItems);
    }

    /**
     * Parse a single item: SKU-opt~val*qty-opt~val*qty
     */
    protected function parseItem(string $item): ParsedItem
    {
        // First hyphen separates base SKU from options
        // But base SKU might contain hyphens if it's a lineage SKU (ORGORG-WBUTS-PROD)
        // So we need to find where options start (first segment with ~)

        $parts = explode('-', $item);
        $baseParts = [];
        $optionParts = [];
        $inOptions = false;

        foreach ($parts as $part) {
            if (! $inOptions && ! str_contains($part, '~')) {
                $baseParts[] = $part;
            } else {
                $inOptions = true;
                $optionParts[] = $part;
            }
        }

        $baseSku = implode('-', $baseParts);
        $options = [];

        // Parse each option: opt~val*qty
        foreach ($optionParts as $optPart) {
            $option = $this->parseOption($optPart);
            if ($option !== null) {
                $options[] = $option;
            }
        }

        return new ParsedItem(
            baseSku: strtoupper($baseSku),
            options: $options
        );
    }

    /**
     * Parse a single option: opt~val*qty
     */
    protected function parseOption(string $optString): ?SkuOption
    {
        // Match: code~value*quantity (quantity optional)
        if (! preg_match('/^([a-z_][a-z0-9_]*)~([^*]+)(?:\*(\d+))?$/i', $optString, $matches)) {
            return null;
        }

        return new SkuOption(
            code: strtolower($matches[1]),
            value: $matches[2],
            quantity: isset($matches[3]) ? (int) $matches[3] : 1
        );
    }

    /**
     * Hash bundle for discount lookup (strips human choices).
     *
     * @param  array<ParsedItem>  $items
     */
    protected function hashBundle(array $items): string
    {
        $baseSkus = collect($items)
            ->map(fn (ParsedItem $item) => strtoupper($item->baseSku))
            ->sort()
            ->implode('|');

        return hash('sha256', $baseSkus);
    }

    /**
     * Validate compound SKU format.
     *
     * @return array{valid: bool, errors: array}
     */
    public function validate(string $compoundSku): array
    {
        $errors = [];

        if (strlen($compoundSku) > 1024) {
            $errors[] = 'Compound SKU exceeds maximum length of 1024 characters.';
        }

        // Try to parse and check for issues
        $result = $this->parse($compoundSku);

        if ($result->count() === 0) {
            $errors[] = 'No valid items found in SKU string.';
        }

        // Check each item has a base SKU
        foreach ($result->collect() as $item) {
            if ($item instanceof BundleItem) {
                foreach ($item->items as $bundleItem) {
                    if (empty($bundleItem->baseSku)) {
                        $errors[] = 'Bundle contains item with empty base SKU.';
                    }
                }
            } elseif (empty($item->baseSku)) {
                $errors[] = 'Item has empty base SKU.';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
