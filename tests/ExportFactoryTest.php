<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Magento\MediaStorage\Model\File\Storage\DatabaseFactory;
use PHPUnit\Framework\TestCase;

class ExportFactoryTest extends TestCase
{
    /** @var MagentoExportFactory */
    private $factory;

    /** @var TestDirectory */
    private $testDirectory;

    /** @var TestDb */
    private $testDb;

    protected function setUp()
    {
        $this->factory = new MagentoExportFactory();
        $this->testDirectory = TestDirectory::create();
        $this->testDb = new TestDb();
    }

    /** @test */
    public function createdTargetExportDirectory()
    {
        $directory = $this->testDirectory->resolvePath('export-target/dir');

        $this->factory->create($directory, $this->testDb->createMagentoOneConnection());

        $this->assertDirectoryExists($directory);
    }
}
