<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Zend\Db\Adapter\Adapter;

class ImportFactory
{
    public function create(string $directory, Adapter $adapter)
    {
        return new Import(
            new CsvFactory($directory),
            EavMetadataImport::createFromAdapter($adapter),
            CategoryImport::createFromAdapter($adapter),
            ProductImport::createFromAdapter($adapter),
            CustomerImport::createFromAdapter($adapter)
        );
    }
}
