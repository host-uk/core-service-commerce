<?php

declare(strict_types=1);

use Core\Commerce\Data\BundleItem;
use Core\Commerce\Data\ParsedItem;
use Core\Commerce\Data\SkuOption;
use Core\Commerce\Services\SkuBuilderService;
use Core\Commerce\Services\SkuParserService;

describe('Compound SKU Parser', function () {
    beforeEach(function () {
        $this->parser = app(SkuParserService::class);
    });

    test('parses simple SKU without options', function () {
        $result = $this->parser->parse('LAPTOP');

        expect($result->count())->toBe(1);
        expect($result->items[0])->toBeInstanceOf(ParsedItem::class);
        expect($result->items[0]->baseSku)->toBe('LAPTOP');
        expect($result->items[0]->options)->toBeEmpty();
    });

    test('parses SKU with single option', function () {
        $result = $this->parser->parse('LAPTOP-ram~16gb');

        expect($result->count())->toBe(1);
        expect($result->items[0]->baseSku)->toBe('LAPTOP');
        expect($result->items[0]->options)->toHaveCount(1);
        expect($result->items[0]->options[0]->code)->toBe('ram');
        expect($result->items[0]->options[0]->value)->toBe('16gb');
        expect($result->items[0]->options[0]->quantity)->toBe(1);
    });

    test('parses SKU with multiple options', function () {
        $result = $this->parser->parse('LAPTOP-ram~16gb-ssd~512gb-color~silver');

        expect($result->count())->toBe(1);
        expect($result->items[0]->options)->toHaveCount(3);
        expect($result->items[0]->getOption('ram')->value)->toBe('16gb');
        expect($result->items[0]->getOption('ssd')->value)->toBe('512gb');
        expect($result->items[0]->getOption('color')->value)->toBe('silver');
    });

    test('parses option with quantity', function () {
        $result = $this->parser->parse('LAPTOP-cover~black*2');

        expect($result->items[0]->options[0]->code)->toBe('cover');
        expect($result->items[0]->options[0]->value)->toBe('black');
        expect($result->items[0]->options[0]->quantity)->toBe(2);
    });

    test('parses multiple comma-separated items', function () {
        $result = $this->parser->parse('LAPTOP-ram~16gb,MOUSE,PAD-size~xl');

        expect($result->count())->toBe(3);
        expect($result->items[0]->baseSku)->toBe('LAPTOP');
        expect($result->items[1]->baseSku)->toBe('MOUSE');
        expect($result->items[2]->baseSku)->toBe('PAD');
        expect($result->hasBundles())->toBeFalse();
    });

    test('parses pipe-separated bundle', function () {
        $result = $this->parser->parse('LAPTOP-ram~16gb|MOUSE|PAD');

        expect($result->count())->toBe(1);
        expect($result->hasBundles())->toBeTrue();
        expect($result->items[0])->toBeInstanceOf(BundleItem::class);

        $bundle = $result->items[0];
        expect($bundle->count())->toBe(3);
        expect($bundle->getBaseSkus())->toBe(['LAPTOP', 'MOUSE', 'PAD']);
    });

    test('bundle hash is consistent regardless of order', function () {
        $result1 = $this->parser->parse('LAPTOP|MOUSE|PAD');
        $result2 = $this->parser->parse('PAD|LAPTOP|MOUSE');

        expect($result1->items[0]->hash)->toBe($result2->items[0]->hash);
    });

    test('parses mixed bundles and singles', function () {
        $result = $this->parser->parse('LAPTOP|MOUSE,HDMI-length~2m');

        expect($result->count())->toBe(2);
        expect($result->items[0])->toBeInstanceOf(BundleItem::class);
        expect($result->items[1])->toBeInstanceOf(ParsedItem::class);
    });

    test('preserves entity lineage in base SKU', function () {
        // SKU with lineage: ORGORG-WBUTS-PROD500
        $result = $this->parser->parse('ORGORG-WBUTS-PROD500-ram~16gb');

        expect($result->items[0]->baseSku)->toBe('ORGORG-WBUTS-PROD500');
        expect($result->items[0]->options)->toHaveCount(1);
    });

    test('validates SKU format', function () {
        $valid = $this->parser->validate('LAPTOP-ram~16gb');
        expect($valid['valid'])->toBeTrue();

        $invalid = $this->parser->validate('');
        expect($invalid['valid'])->toBeFalse();
    });

    test('round trips through parse and toString', function () {
        $original = 'LAPTOP-ram~16gb-ssd~512gb';
        $result = $this->parser->parse($original);

        expect($result->toString())->toBe($original);
    });
});

describe('Compound SKU Builder', function () {
    beforeEach(function () {
        $this->builder = app(SkuBuilderService::class);
    });

    test('builds simple item', function () {
        $sku = $this->builder->build([
            ['base_sku' => 'laptop'],
        ]);

        expect($sku)->toBe('LAPTOP');
    });

    test('builds item with options', function () {
        $sku = $this->builder->build([
            [
                'base_sku' => 'laptop',
                'options' => [
                    ['code' => 'ram', 'value' => '16gb'],
                    ['code' => 'ssd', 'value' => '512gb'],
                ],
            ],
        ]);

        expect($sku)->toBe('LAPTOP-ram~16gb-ssd~512gb');
    });

    test('builds item with quantity on option', function () {
        $sku = $this->builder->build([
            [
                'base_sku' => 'laptop',
                'options' => [
                    ['code' => 'cover', 'value' => 'black', 'quantity' => 2],
                ],
            ],
        ]);

        expect($sku)->toBe('LAPTOP-cover~black*2');
    });

    test('builds multiple items comma-separated', function () {
        $sku = $this->builder->build([
            ['base_sku' => 'laptop'],
            ['base_sku' => 'mouse'],
        ]);

        expect($sku)->toBe('LAPTOP,MOUSE');
    });

    test('builds bundle with same group', function () {
        $sku = $this->builder->build([
            ['base_sku' => 'laptop', 'bundle_group' => 'cyber'],
            ['base_sku' => 'mouse', 'bundle_group' => 'cyber'],
            ['base_sku' => 'hdmi'],  // standalone
        ]);

        expect($sku)->toBe('LAPTOP|MOUSE,HDMI');
    });

    test('adds entity lineage', function () {
        $sku = $this->builder->addLineage('PROD500', ['ORGORG', 'WBUTS']);

        expect($sku)->toBe('ORGORG-WBUTS-PROD500');
    });

    test('builds with lineage', function () {
        $sku = $this->builder->buildWithLineage(
            ['ORGORG', 'WBUTS'],
            [
                [
                    'base_sku' => 'PROD500',
                    'options' => [['code' => 'size', 'value' => 'xl']],
                ],
            ]
        );

        expect($sku)->toBe('ORGORG-WBUTS-PROD500-size~xl');
    });

    test('generates bundle hash', function () {
        $hash = $this->builder->generateBundleHash(['laptop', 'mouse', 'pad']);

        // Hash is deterministic
        expect($hash)->toBe($this->builder->generateBundleHash(['PAD', 'LAPTOP', 'MOUSE']));
        expect(strlen($hash))->toBe(64);  // SHA256
    });

    test('creates option and item helpers', function () {
        $option = $this->builder->option('ram', '16gb', 2);
        expect($option)->toBeInstanceOf(SkuOption::class);
        expect($option->toString())->toBe('ram~16gb*2');

        $item = $this->builder->item('LAPTOP', [$option]);
        expect($item)->toBeInstanceOf(ParsedItem::class);
        expect($item->toString())->toBe('LAPTOP-ram~16gb*2');
    });
});

describe('SKU Parse Result', function () {
    test('collects all base SKUs', function () {
        $parser = app(SkuParserService::class);
        $result = $parser->parse('LAPTOP|MOUSE,HDMI,PAD');

        expect($result->getAllBaseSkus())->toBe(['LAPTOP', 'MOUSE', 'HDMI', 'PAD']);
    });

    test('counts products correctly', function () {
        $parser = app(SkuParserService::class);
        $result = $parser->parse('LAPTOP|MOUSE|PAD,HDMI');

        expect($result->count())->toBe(2);  // 1 bundle + 1 single
        expect($result->productCount())->toBe(4);  // 3 in bundle + 1 single
    });

    test('extracts bundle hashes', function () {
        $parser = app(SkuParserService::class);
        $result = $parser->parse('LAPTOP|MOUSE,KEYBOARD|PAD');

        $hashes = $result->getBundleHashes();
        expect($hashes)->toHaveCount(2);
    });
});
