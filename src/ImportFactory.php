<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Laminas\Db\Adapter\Adapter;

class ImportFactory
{
    public function create(string $directory, ConnectionPool $connectionPool, bool $decodeData = false)
    {
        $adapter = $connectionPool->createConnection();
        $readAdapter = $connectionPool->createConnection();
        return new Import(
            new CsvFactory($directory, $decodeData),
            EavMetadataImport::createFromAdapter($adapter),
            CategoryImport::createFromAdapter($adapter, $readAdapter),
            ProductImport::createFromAdapter($adapter),
            CustomerImport::createFromAdapter($adapter)
        );
    }
}
