<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;

class CsvWriterTest extends TestCase
{
    /** @var CsvFactory */
    private $factory;

    /** @var TestDirectory */
    private $testDirectory;

    protected function setUp()
    {
        $this->testDirectory = TestDirectory::create();
        $this->factory = new CsvFactory($this->testDirectory->resolvePath(''));
    }

    /** @test */
    public function writesDataIntoCsvFileBasedOnHeaderSpecification()
    {
        $writer = $this->factory->createWriter('file_with_data.csv', ['name', 'status', 'value']);
        $writer->write(['name' => 'Foo', 'value' => 'bar', 'status' => 0, 'other' => 'stuff']);

        $this->assertEquals(
            [['name' => 'Foo', 'value' => 'bar', 'status' => 0]],
            iterator_to_array($this->factory->createReader('file_with_data.csv'))
        );
    }

    /** @test */
    public function filtersOutputBasedOnSkipFilters()
    {
        $writer = $this->factory
            ->withSkip('file_with_data.csv', ['value' => ['bar2'], 'status' => [1]])
            ->withSkip('file_with_data.csv', ['name' => ['Foo2.3']])
            ->createWriter('file_with_data.csv', ['name', 'value', 'status']);
        ;

        $writer->write(['name' => 'Foo1', 'value' => 'bar1', 'status' => 0]);
        $writer->write(['name' => 'Foo2', 'value' => 'bar2', 'status' => 1]);
        $writer->write(['name' => 'Foo3', 'value' => 'bar3', 'status' => 1]);
        $writer->write(['name' => 'Foo2.1', 'value' => 'bar3', 'status' => 1]);
        $writer->write(['name' => 'Foo2.3', 'value' => 'bar3', 'status' => 1]);
        $writer->write(['name' => 'Foo4', 'value' => 'bar4', 'status' => 0]);

        $this->assertEquals(
            [
                ['name' => 'Foo1', 'value' => 'bar1', 'status' => 0],
                ['name' => 'Foo3', 'value' => 'bar3', 'status' => 1],
                ['name' => 'Foo2.1', 'value' => 'bar3', 'status' => 1],
                ['name' => 'Foo4', 'value' => 'bar4', 'status' => 0],
            ],
            iterator_to_array(
                $this->factory->createReader('file_with_data.csv')
            )
        );
    }

    /** @test */
    public function allowsToSpecifyIncludeRulesForColumns()
    {
        $writer = $this->factory
            ->withSkip('file_with_data.csv', ['!value' => ['bar2']])
            ->createWriter('file_with_data.csv', ['name', 'value', 'status']);
        ;

        $writer->write(['name' => 'Foo1', 'value' => 'bar1', 'status' => 0]);
        $writer->write(['name' => 'Foo2', 'value' => 'bar2', 'status' => 1]);
        $writer->write(['name' => 'Foo3', 'value' => 'bar2', 'status' => 1]);
        $writer->write(['name' => 'Foo2.1', 'value' => 'bar3', 'status' => 1]);
        $writer->write(['name' => 'Foo2.3', 'value' => 'bar3', 'status' => 1]);
        $writer->write(['name' => 'Foo4', 'value' => 'bar4', 'status' => 0]);

        $this->assertEquals(
            [
                ['name' => 'Foo2', 'value' => 'bar2', 'status' => 1],
                ['name' => 'Foo3', 'value' => 'bar2', 'status' => 1],
            ],
            iterator_to_array(
                $this->factory->createReader('file_with_data.csv')
            )
        );
    }

    /** @test */
    public function mapsValuesInOutput()
    {
        $writer = $this->factory
            ->withMap('file_with_data.csv', 'name', ['Foo2' => 'Foo2222'])
            ->withMap('file_with_data.csv', 'status', [-1 => 1])
            ->createWriter('file_with_data.csv', ['name', 'value', 'status']);
        ;

        $writer->write(['name' => 'Foo1', 'value' => 'bar1', 'status' => 0]);
        $writer->write(['name' => 'Foo2', 'value' => 'bar2', 'status' => 1]);
        $writer->write(['name' => 'Foo3', 'value' => 'bar2', 'status' => -1]);
        $writer->write(['name' => 'Foo4', 'value' => 'bar4', 'status' => 0]);

        $this->assertEquals(
            [
                ['name' => 'Foo1', 'value' => 'bar1', 'status' => 0],
                ['name' => 'Foo2222', 'value' => 'bar2', 'status' => 1],
                ['name' => 'Foo3', 'value' => 'bar2', 'status' => 1],
                ['name' => 'Foo4', 'value' => 'bar4', 'status' => 0],
            ],
            iterator_to_array(
                $this->factory->createReader('file_with_data.csv')
            )
        );
    }

}
