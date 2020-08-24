<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;

class MergedRowMapperTest extends TestCase
{
    /** @var MergedRowMapper */
    private $mergedMapper;

    protected function setUp()
    {
        $this->mergedMapper = new MergedRowMapper();
    }

    /** @test */
    public function returnsDataStructureAsNoMergedDataProvided()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20']
            ],
            iterator_to_array(
                $this->mergedMapper->apply([
                    ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                    ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20']
                ])
            )
        );
    }

    /** @test */
    public function appendsMergedStructureAppliedStructure()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => '', 'value' => 'value1'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => 'en_us', 'value' => 'value2'],
                ['sku' => 'sku2', 'attribute' => 'attribute1', 'store' => 'en_uk', 'value' => 'value3'],
            ],
            iterator_to_array(
                $this->mergedMapper
                    ->withCsvFile(__DIR__ . '/fixture/file1.csv')
                    ->apply([
                        ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                        ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20']
                    ])
            )
        );
    }

    /** @test */
    public function mergesAllAppendedRowsStructuresToAppliedStructure()
    {
        $this->assertEquals(
            [
                ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => '', 'value' => 'value1'],
                ['sku' => 'sku1', 'attribute' => 'attribute1', 'store' => 'en_us', 'value' => 'value2'],
                ['sku' => 'sku2', 'attribute' => 'attribute1', 'store' => 'en_uk', 'value' => 'value3'],
                ['sku' => 'sku1', 'attribute' => 'attribute2', 'store' => '', 'value' => 'value1.1'],
                ['sku' => 'sku1', 'attribute' => 'attribute2', 'store' => 'en_us', 'value' => 'value1.2'],
                ['sku' => 'sku2', 'attribute' => 'attribute2', 'store' => 'en_uk', 'value' => 'value1.3'],
            ],
            iterator_to_array(
                $this->mergedMapper
                    ->withCsvFile(
                        __DIR__ . '/fixture/file1.csv'
                    )
                    ->withCsvFile(
                        __DIR__ . '/fixture/file2.csv'
                    )
                    ->apply([
                        ['sku' => 'sku10', 'attribute' => 'attribute10', 'store' => '', 'value' => '10'],
                        ['sku' => 'sku20', 'attribute' => 'attribute20', 'store' => '', 'value' => '20']
                    ])
            )
        );
    }
}
