<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;


use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;

class TableResolverFactory
{
    /**
     * @var Sql
     */
    private $sql;

    public function __construct(Sql $sql)
    {
        $this->sql = $sql;
    }

    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(new Sql($adapter));
    }

    public function createSingleValueResolver(string $tableName, string $searchField, string $targetField): SingleTableResolver
    {
        return new SingleTableResolver($this->sql, $tableName, $searchField, $targetField);
    }

    public function createCombinedValueResolver(
        string $tableName,
        string $targetField,
        string $searchField,
        string $foreignField,
        IdResolver $foreignResolver
    ) {
        return new CombinedTableResolver(
            $this->sql,
            $tableName,
            $searchField,
            $targetField,
            $foreignField,
            $foreignResolver
        );
    }
}
