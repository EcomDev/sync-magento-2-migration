<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;

use PHPUnit\Framework\TestCase;

class DescriptionMapperTest extends TestCase
{
    /** @var DescriptionMapper */
    private $descriptionMapper;

    protected function setUp()
    {
        $this->descriptionMapper = new DescriptionMapper();
    }

    /** @test */
    public function replacesPStrongWithH2()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'short_description', 'store' => '', 'value' => 'Sku1 Short Desc'],
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => '<h2>Sku1 Description</h2>'],
                ['sku' => 'sku2', 'attribute' => 'short_description', 'store' => '', 'value' => 'Sku2 Short Desc'],
                ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description'],
            ],
            $this->asArray(
                $this->descriptionMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'short_description', 'store' => '', 'value' => 'Sku1 Short Desc'],
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => '<p><strong>Sku1 Description</strong></p>'],
                    ['sku' => 'sku2', 'attribute' => 'short_description', 'store' => '', 'value' => 'Sku2 Short Desc'],
                    ['sku' => 'sku2', 'attribute' => 'description', 'store' => '', 'value' => 'Sku2 Description']
                ))
            )
        );
    }

    /** @test */
    public function replacesH4withH3()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => '<h3 itemprop="name">Sku1 Description</h3>'],
            ],
            $this->asArray(
                $this->descriptionMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'description', 'store' => '', 'value' => '<h4>Sku1 Description</h4>']
                ))
            )
        );
    }

    /** @test */
    public function replacesPBwithH2()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'short_description', 'store' => '', 'value' => '<h2>Sku1 Description</h2>'],
            ],
            $this->asArray(
                $this->descriptionMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'short_description', 'store' => '', 'value' => '<p><b>Sku1 Description</b></p>']
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
