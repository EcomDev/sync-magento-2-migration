<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Laminas\Db\Adapter\Adapter;

class DbFactory
{
    public function createConnection(string $host, string $user, string $password, string $dbName): Adapter
    {
        return $this->crateConnectionPool($host, $user, $password, $dbName)->createConnection();
    }

    public function crateConnectionPool(string $host, string $user, string $password, string $dbName): ConnectionPool
    {
        return new ConnectionPool($host, $user, $password, $dbName);
    }
}
