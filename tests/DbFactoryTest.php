<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;
use Zend\Db\Adapter\Adapter;

class DbFactoryTest extends TestCase
{
    /** @var DbFactory */
    private $factory;

    protected function setUp()
    {
        $this->factory = new DbFactory();
    }

    /** @test */
    public function createsDatabaseConnection()
    {
        $connection = $this->createConnection();
        $this->assertInstanceOf(Adapter::class, $connection);
    }

    /** @test */
    public function executesDatabaseQuery()
    {
        $this->assertEquals(
            [['1+1' => 2]],
            iterator_to_array($this->createConnection()->query('SELECT 1+1')->execute())
        );
    }

    private function createConnection(): Adapter
    {
        return $this->factory->createConnection(
            $_ENV['DB_HOST'],
            $_ENV['DB_USER'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_NAME']
        );
    }
}
