<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;

use PHPUnit\Framework\TestCase;

class BaseCostMapperTest extends TestCase
{
    /** @var BaseCostMapper */
    private $baseCostMapper;

    protected function setUp()
    {
        $this->baseCostMapper = new BaseCostMapper();
    }

    /** @test */
    public function filtersOutBaseCostWhenCurrencyAttributeIsMissingCurrency()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 200]
            ],
            $this->asArray(
                $this->baseCostMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 200]
                ))
            )
        );
    }

    /** @test */
    public function putsBaseCostIntoCostInAdminAndDefaultStore()
    {
        $this->baseCostMapper = $this->baseCostMapper->withDefaultStore('us');

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => '', 'value' => 100],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => 'us', 'value' => 100],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],

                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 200]
            ],
            $this->asArray(
                $this->baseCostMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku1', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => ''],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 200]
                ))
            )
        );
    }

    /** @test */
    public function createsConvertedValueForTargetStoreFromBaseCost()
    {
        $this->baseCostMapper = $this->baseCostMapper->withStore('uk', 0.77)
            ->withStore('eu', 0.83)
                                    ;

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => '', 'value' => 100],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => 'uk', 'value' => 77],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => 'eu', 'value' => 83],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],

                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200]
            ],
            $this->asArray(
                $this->baseCostMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku1', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => ''],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200]
                ))
            )
        );
    }

    /** @test */
    public function convertsBaseCostUsingCurrencyMap()
    {
        $this->baseCostMapper = $this->baseCostMapper->withCurrencyConversion('GBP', 1.23)
            ->withCurrencyConversion('EUR', 1.17)
        ;

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => '', 'value' => 123],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],

                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku2', 'attribute' => 'cost', 'store' => '', 'value' => 117],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],

                ['sku' => 'sku3', 'attribute' => 'name', 'store' => '', 'value' => 'Sku3 Name'],
                ['sku' => 'sku3', 'attribute' => 'description', 'store' => '', 'value' => 'Sku3 Description'],
                ['sku' => 'sku3', 'attribute' => 'cost', 'store' => '', 'value' => 100],
                ['sku' => 'sku3', 'attribute' => 'price', 'store' => '', 'value' => 200]
            ],
            $this->asArray(
                $this->baseCostMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku1', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => 'GBP'],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku2', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku2', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => 'EUR'],
                    ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],
                    ['sku' => 'sku3', 'attribute' => 'name', 'store' => '', 'value' => 'Sku3 Name'],
                    ['sku' => 'sku3', 'attribute' => 'description', 'store' => '', 'value' => 'Sku3 Description'],
                    ['sku' => 'sku3', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku3', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => ''],
                    ['sku' => 'sku3', 'attribute' => 'price', 'store' => '', 'value' => 200]
                ))
            )
        );
    }

    /** @test */
    public function waitsForCompleteBaseCurrencyEvenWhenItIsApartInTheDataSet()
    {
        $this->baseCostMapper = $this->baseCostMapper->withCurrencyConversion('GBP', 1.23)
            ->withCurrencyConversion('EUR', 1.17)
        ;

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],

                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],

                ['sku' => 'sku3', 'attribute' => 'name', 'store' => '', 'value' => 'Sku3 Name'],
                ['sku' => 'sku3', 'attribute' => 'description', 'store' => '', 'value' => 'Sku3 Description'],
                ['sku' => 'sku3', 'attribute' => 'price', 'store' => '', 'value' => 200],

                ['sku' => 'sku1', 'attribute' => 'cost', 'store' => '', 'value' => 123],
                ['sku' => 'sku2', 'attribute' => 'cost', 'store' => '', 'value' => 117],
                ['sku' => 'sku3', 'attribute' => 'cost', 'store' => '', 'value' => 100],
            ],
            $this->asArray(
                $this->baseCostMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => 'Sku1 Description'],
                    ['sku' => 'sku1', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku1', 'attribute' => 'price', 'store' => '', 'value' => 150],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
                    ['sku' => 'sku2', 'attribute' => 'base_cost', 'store' => '', 'value' => 100],
                    ['sku' => 'sku2', 'attribute' => 'price', 'store' => '', 'value' => 200],
                    ['sku' => 'sku3', 'attribute' => 'name', 'store' => '', 'value' => 'Sku3 Name'],
                    ['sku' => 'sku3', 'attribute' => 'description', 'store' => '', 'value' => 'Sku3 Description'],
                    ['sku' => 'sku3', 'attribute' => 'price', 'store' => '', 'value' => 200],
                    ['sku' => 'sku1', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => 'GBP'],
                    ['sku' => 'sku2', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => 'EUR'],
                    ['sku' => 'sku3', 'attribute' => 'base_cost_currency', 'store' => '', 'value' => ''],
                    ['sku' => 'sku3', 'attribute' => 'base_cost', 'store' => '', 'value' => 100]
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
