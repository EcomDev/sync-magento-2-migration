<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;

use Laminas\Db\Sql\Sql;

class SingleTableResolver implements IdResolver
{
    /**
     * @var Sql
     */
    private $sql;
    /**
     * @var string
     */
    private $tableName;
    /**
     * @var string
     */
    private $sourceField;
    /**
     * @var string
     */
    private $targetField;

    /** @var string[] */
    private $unresolvedValues;

    /** @var int[] */
    private $resolvedValues = [];

    /** @var array[] */
    private $incrementRow = [];

    /**
     * @var array
     */
    private $filter = [];

    public function __construct(Sql $sql, string $tableName, string $sourceField, string $targetField)
    {
        $this->sql = $sql;
        $this->tableName = $tableName;
        $this->sourceField = $sourceField;
        $this->targetField = $targetField;
    }

    public function withAutoIncrement(array $defaultRow): self
    {
        $resolver = clone $this;
        $resolver->incrementRow = $defaultRow;
        return $resolver;
    }

    /**
     * @throws IdentifierNotResolved when value does not exists and cannot be generated
     */
    public function resolve(Identifier $value): int
    {
        $this->resolveValues();

        return $value->findValue($this->resolvedValues);
    }

    private function resolveValues()
    {
        if (!$this->unresolvedValues) {
            return;
        }

        $resolveValue = $this->unresolvedValues;
        $this->unresolvedValues = [];

        $inCondition = [];
        foreach ($resolveValue as $key => $value) {
            unset($value);
            $inCondition[] = (string)$key;
        }

        $select = $this->sql->select($this->tableName)
            ->columns([$this->targetField, $this->sourceField])
            ->where([$this->sourceField => $inCondition] + $this->filter);

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $this->resolvedValues[$row[$this->sourceField]] = (int)$row[$this->targetField];
            unset($resolveValue[$row[$this->sourceField]]);
        }

        if ($this->incrementRow && $resolveValue) {
            $this->sql->getAdapter()->getDriver()->getConnection()->beginTransaction();

            $incrementRow = $this->incrementRow;
            unset($incrementRow[$this->sourceField]);

            $columns = array_keys($incrementRow);
            $columns[] = $this->sourceField;
            $baseRow = array_values($incrementRow);

            $insert = InsertOnDuplicate::create($this->tableName, $columns);
            $inCondition = [];

            foreach (array_keys($resolveValue) as $key) {
                $row = $baseRow;
                $row[] = $key;
                $inCondition[] = (string)$key;
                $insert = $insert->withRow(...$row);
            }

            $insert->executeIfNotEmpty($this->sql);

            $select = $this->sql->select($this->tableName)
                ->columns([$this->targetField, $this->sourceField])
                ->where([$this->sourceField => $inCondition] + $this->filter);

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $this->resolvedValues[$row[$this->sourceField]] = (int)$row[$this->targetField];
            }

            $this->sql->getAdapter()->getDriver()->getConnection()->rollback();
        }
    }

    /**
     * Creates a new resolvable id
     */
    public function unresolved($value): Identifier
    {
        if (isset($this->resolvedValues[$value])) {
            return new ResolvedIdentifier($this->resolvedValues[$value]);
        }

        $this->unresolvedValues[$value] = $this->unresolvedValues[$value]
            ?? new UnresolvedIdentifier($value, [$this, 'removeId']);

        return $this->unresolvedValues[$value];
    }

    public function removeId(string $value): void
    {
        unset($this->resolvedValues[$value], $this->unresolvedValues[$value]);
    }

    public function withFilter(array $filter): self
    {
        $resolver = clone $this;
        $resolver->filter = $filter;
        return $resolver;
    }
}
