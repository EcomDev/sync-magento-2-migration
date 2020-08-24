<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;

use PHPUnit\Framework\TestCase;

class PriceMapperTest extends TestCase
{
    /** @var PriceMapper */
    private $priceMapper;

    protected function setUp()
    {
        $this->priceMapper = new PriceMapper();
    }

    /** @test */
    public function whenNoStoreConversionIsProvidedDataReturnedWithoutSpecialPrice()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200]
            ],
            $this->asArray(
                $this->priceMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku1', 'attribute' => 'special_price', 'store' => '', 'value' => 120],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],
                    ['sku' => 'sku2', 'attribute' => 'special_price', 'store' => '', 'value' => 160]
                ))
            )
        );
    }

    /** @test */
    public function whenStoreConversionRateIsProvidedPriceAndSpecialPriceGetsConverted()
    {
        $this->priceMapper = $this->priceMapper->withStore('us', 1)
            ->withStore('eu', 0.83)
            ->withStore('uk', 0.77)
        ;

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => 'us', 'value' => 150],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => 'eu', 'value' => 124.5],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => 'uk', 'value' => 115.5],
                ['sku' => 'sku1', 'attribute' => 'special_price', 'store' => 'us', 'value' => 120],
                ['sku' => 'sku1', 'attribute' => 'special_price', 'store' => 'eu', 'value' => 99.6],
                ['sku' => 'sku1', 'attribute' => 'special_price', 'store' => 'uk', 'value' => 92.4],
                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => 'us', 'value' => 200],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => 'eu', 'value' => 166],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => 'uk', 'value' => 154],
                ['sku' => 'sku2', 'attribute' => 'special_price', 'store' => 'us', 'value' => 160],
                ['sku' => 'sku2', 'attribute' => 'special_price', 'store' => 'eu', 'value' => 132.8],
                ['sku' => 'sku2', 'attribute' => 'special_price', 'store' => 'uk', 'value' => 123.2]
            ],
            $this->asArray(
                $this->priceMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku1', 'attribute' => 'special_price', 'store' => '', 'value' => 120],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],
                    ['sku' => 'sku2', 'attribute' => 'special_price', 'store' => '', 'value' => 160]
                ))
            )
        );
    }

    private function asArray(iterable $traversable): array
    {
        return iterator_to_array($traversable);
    }

    private function arrayIteratable(array ...$items): iterable
    {
        return new \ArrayIterator($items);
    }
}
