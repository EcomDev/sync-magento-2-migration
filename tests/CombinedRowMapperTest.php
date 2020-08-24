<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;

class CombinedRowMapperTest extends TestCase
{
    /** @var CombinedRowMapper */
    private $rowMapper;

    protected function setUp()
    {
        $this->rowMapper = new CombinedRowMapper();
    }

    /** @test */
    public function passesAsIsNoMappersProvided()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name']
            ],
            $this->asArray(
                $this->rowMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name']
                ))
            )
        );
    }

    /** @test */
    public function pipesRowMappersTogether()
    {
        $rowMapper = $this->rowMapper
            ->pipe((new MergedRowMapper())->withCsvFile(__DIR__ . '/fixture/file1.csv'))
            ->pipe((new MergedRowMapper())->withCsvFile(__DIR__ . '/fixture/file2.csv'))
        ;

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => '', 'value' => 'value1'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => 'en_us', 'value' => 'value2'],
                ['sku' => 'sku2', 'attribute' => 'attribute1', 'store' => 'en_uk', 'value' => 'value3'],
                ['sku' => 'sku1', 'attribute' => 'attribute2', 'store' => '', 'value' => 'value1.1'],
                ['sku' => 'sku1', 'attribute' => 'attribute2', 'store' => 'en_us', 'value' => 'value1.2'],
                ['sku' => 'sku2', 'attribute' => 'attribute2', 'store' => 'en_uk', 'value' => 'value1.3']
            ],
            $this->asArray(
                $rowMapper->apply($this->arrayIteratable(
                    ['sku' => 'sku1', 'attribute' => 'name', 'store' => '', 'value' => 'Sku1 Name'],
                    ['sku' => 'sku2', 'attribute' => 'name', 'store' => '', 'value' => 'Sku2 Name']
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
