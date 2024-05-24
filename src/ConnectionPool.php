<?php

namespace EcomDev\MagentoMigration;

use Laminas\Db\Adapter\Adapter;
use PDO;

class ConnectionPool
{
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database
    ) {

    }

    public function createConnection(): Adapter
    {
        return new Adapter([
            'driver' => 'Pdo_Mysql',
            'hostname' => $this->host,
            'username' => $this->username,
            'password' => $this->password,
            'database' => $this->database,
            'driver_options' => [
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => 0
            ]
        ]);
    }
}
