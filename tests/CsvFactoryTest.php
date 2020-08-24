<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use PHPUnit\Framework\TestCase;

class CsvFactoryTest extends TestCase
{
    /** @var TestDirectory */
    private $testDirectory;

    /** @var CsvFactory */
    private $factory;

    protected function setUp()
    {
        $this->testDirectory = TestDirectory::create();
        $this->factory = new CsvFactory($this->testDirectory->resolvePath(''));
    }

    /** @test */
    public function createsWriterWithNewLineAndCommaSeparatedContent()
    {
        $writer = $this->factory->createWriter('file1.csv', ['one', 'two', 'three']);
        $writer->write(['one' => 'value1', 'two' => 'value2', 'three' => 'value3']);

        $this->assertStringEqualsFile(
            $this->testDirectory->resolvePath('file1.csv'),
            'one,two,three' . PHP_EOL . 'value1,value2,value3' . PHP_EOL
        );
    }

    /** @test */
    public function outputsCsvFileWithMultiLineValues()
    {
        $writer = $this->factory->createWriter('file1.csv', ['one', 'two', 'three']);
        $writer->write(['one' => 'value1', 'two' => "value2\nnewline\nanother one", 'three' => 'value3']);


        $this->assertStringEqualsFile(
            $this->testDirectory->resolvePath('file1.csv'),
            'one,two,three' . PHP_EOL . 'value1,"value2
newline
another one",value3' . PHP_EOL
        );
    }

    /** @test */
    public function enclosesEscapedCharsInOutput()
    {
        $writer = $this->factory->createWriter('file1.csv', ['one', 'two', 'three']);
        $writer->write(['one' => 'value1', 'two' => "value2\\\"\nnewline\nanother one", 'three' => 'value3']);


        $this->assertStringEqualsFile(
            $this->testDirectory->resolvePath('file1.csv'),
            'one,two,three' . PHP_EOL . 'value1,"value2\\""
newline
another one",value3' . PHP_EOL
        );
    }

    /** @test */
    public function readsEscpaedCharOutput()
    {
        file_put_contents(
            $this->testDirectory->resolvePath('file2.csv'),
            'one,two,three' . PHP_EOL . 'value1,"value2\\""
newline
another one",value3' . PHP_EOL
        );


        $this->assertEquals(
            [
                ['one' => 'value1', 'two' => "value2\\\"\nnewline\nanother one", 'three' => 'value3']
            ],
            iterator_to_array($this->factory->createReader('file2.csv'))
        );
    }


    /** @test */
    public function createsReadCsvInstance()
    {
        file_put_contents(
            $this->testDirectory->resolvePath('file2.csv'),
            'item1,item2,item3' . PHP_EOL . 'value1,value2,value3' . PHP_EOL
        );

        $this->assertEquals(
            [
                [
                    'item1' => 'value1',
                    'item2' => 'value2',
                    'item3' => 'value3'
                ],
            ],

            iterator_to_array($this->factory->createReader('file2.csv'))
        );
    }

    /** @test */
    public function readsEmptyFileWhenFileDoesNotExists()
    {
        $this->assertEquals(
            [],
            iterator_to_array(
                $this->factory->createReader($this->testDirectory->resolvePath('file_not-exists.csv'))
            )
        );
    }
}
