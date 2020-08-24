<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Literal;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

class TableRangeConditionGeneratorFactory
{
    /**
     * @var Sql
     */
    private $sql;
    /**
     * @var SelectConditionFactory
     */
    private $conditionFactory;

    public function __construct(Sql $sql, SelectConditionFactory $conditionFactory)
    {
        $this->sql = $sql;
        $this->conditionFactory = $conditionFactory;
    }

    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(new Sql($adapter), new SelectConditionFactory());
    }

    public function createForTable(string $tableName, string $fieldName): TableRangeConditionGenerator
    {
        $select = $this->sql->select($tableName)
            ->columns([
                'min' => new Literal(sprintf('MIN(%s)', $fieldName)),
                'max' => new Literal(sprintf('MAX(%s)', $fieldName)),
            ]);

        list($minValue, $maxValue) = array_values(
            $this->sql->prepareStatementForSqlObject($select)->execute()->current()
        );


        $range = new Literal(sprintf('CEIL(%s / 2000) * 2000', $fieldName));

        $select = $this->sql->select($tableName)
            ->columns([
                'range' => $range
            ])
            ->group($range);
        ;

        $ranges = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $ranges[] = (int)$row['range'];
        }

        return new TableRangeConditionGenerator($this->conditionFactory, (int)$minValue, (int)$maxValue, $ranges);
    }
}
