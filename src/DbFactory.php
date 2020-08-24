<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PDO;
use Zend\Db\Adapter\Adapter;

class DbFactory
{
    public function createConnection(string $host, string $user, string $password, string $dbName): Adapter
    {
        return new Adapter([
            'driver' => 'Pdo_Mysql',
            'hostname' => $host,
            'username' => $user,
            'password' => $password,
            'database' => $dbName,
            'driver_options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => 0
            ]
        ]);
    }
}
