<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;


use Braintree\Exception;
use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Adapter\Driver\DriverInterface;
use Laminas\Db\Adapter\Driver\StatementInterface;
use Laminas\Db\Adapter\ParameterContainer;
use Laminas\Db\Adapter\Platform\PlatformInterface;
use Laminas\Db\Sql\InsertMultiple;
use Laminas\Db\Sql\Sql;

class InsertOnDuplicate extends InsertMultiple
{
    /**
     * @var string[]
     */
    private $onDuplicate = [];
    /**
     * @var IdResolver[]
     */
    private $resolver = [];

    /** @var string[] */
    private $columnNames = [];

    /** @var StatementInterface[] */
    private $statements = [];

    /** @var array */
    private $rows = [];

    /** @var array */
    private $resolvedParams = [];

    /** @var int */
    private $resolvedCount = 0;

    /**
     * @var bool
     */
    private $nullOnUnresolved = false;

    /**
     * @var string[]
     */
    private $formatted = [];

    public static function create(string $tableName, array $columns): self
    {
        $insert = (new self($tableName));
        $insert->columnNames = $columns;
        $insert->columns($columns);
        return $insert;
    }

    public function withRow(...$row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    public function onDuplicate(array $columnsToUpdate): self
    {
        $this->onDuplicate = $columnsToUpdate;
        return $this;
    }


    private function resolveRows()
    {
        foreach ($this->rows as $row) {
            foreach ($row as $index => $value) {
                if ($value instanceof Identifier) {
                    foreach ($this->resolver as $resolver) {
                        try {
                            $row[$index] = $value = $resolver->resolve($value);

                            break;
                        } catch (IdentifierNotResolved $e) {
                            continue;
                        }
                    }

                    if ($value instanceof Identifier) {
                        if ($this->nullOnUnresolved) {
                            $row[$index] = null;
                            continue;
                        }

                        continue 2;
                    }



                }
            }

            $this->resolvedCount++;
            foreach ($row as $index => $value) {
                $columnName = $this->columnNames[$index];

                if (isset($this->formatted[$columnName]) && $value !== null) {
                    $value = sprintf($this->formatted[$columnName], $value);
                }

                $this->resolvedParams[] = $value;
            }
        }

        $this->rows = [];
    }

    public function flushIfLimitReached(Sql $sql, $limit = 2000): self
    {
        if (count($this->rows) + $this->resolvedCount > $limit) {
            $this->resolveRows();
            $this->executeResolvedValues($limit, $sql->getAdapter());
        }

        return $this;
    }

    public function executeIfNotEmpty(Sql $sql): self
    {
        $this->resolveRows();
        if ($this->resolvedCount) {
            $this->executeResolvedValues($this->resolvedCount, $sql->getAdapter());
        }


        return $this;
    }

    private function limitStatement($limit, AdapterInterface $adapter): StatementInterface
    {
        if (!isset($this->statements[$limit])) {
            $this->statements[$limit] = $adapter->getDriver()->createStatement(
                $this->generateInsertSQL($limit, $adapter)
            );
        }

        return $this->statements[$limit];
    }


    public function withResolver(IdResolver $resolver): self
    {
        $insert = clone $this;
        $insert->resolver[] = $resolver;
        return $insert;
    }

    public function withNullOnUnresolved(): self
    {
        $insert = clone $this;
        $insert->nullOnUnresolved = true;
        return $insert;
    }

    public function withFormatted(string $field, string $format): self
    {
        $insert = clone $this;
        $insert->formatted[$field] = $format;
        return $insert;
    }

    private function generateInsertStatement(PlatformInterface $platform, DriverInterface $driver = null, ParameterContainer $parameterContainer = null)
    {
        if ($this->select) {
            return;
        }
        if (!$this->columns) {
            throw new \Laminas\Db\Exception\InvalidArgumentException('values or select should be present');
        }

        $columns = array();
        $values  = array();

        if (empty($this->valueRows)) {
            return '';    //TODO Test that
        }

        $prepareColumns = true;
        foreach ($this->valueRows as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new \Laminas\Db\Exception\InvalidArgumentException('values must be arrays for multi-insertion');
            }
            $subValues = array();
            ksort($row); // Make sure columns always appear in the same order

            foreach($row as $col => $subValue) {
                if ($prepareColumns) {
                    $columns[] = $platform->quoteIdentifier($col);
                }

                if (is_scalar($subValue) && $parameterContainer) {
                    $subValues[] = $driver->formatParameterName($col . $rowIndex);
                    $parameterContainer[$col . $rowIndex] = $subValue;
                } else {
                    $subValues[] = $this->resolveColumnValue(
                        $subValue,
                        $platform,
                        $driver,
                        $parameterContainer
                    );
                }
            }
            $values[] = implode(', ', $subValues);
            $prepareColumns = false;
        }
        return sprintf(
            $this->specifications[static::SPECIFICATION_INSERT],
            $this->resolveTable($this->table, $platform, $driver, $parameterContainer),
            implode(', ', $columns),
            implode('), (', $values)
        );
    }


    public function withAssocRow(array $row)
    {
        $flatRow = [];
        foreach ($this->columnNames  as $name) {
            $flatRow[] = $row[$name] ?? null;
        }

        $this->rows[] = $flatRow;
        return $this;
    }

    private function generateInsertSQL(int $rowsCount, AdapterInterface $adapter)
    {
        $rowTemplate = sprintf('(%s)', implode(',', array_fill(0, count($this->columnNames), '?')));

        $statement = sprintf(
            'INSERT INTO %s (%s) VALUES %s%s',
            $this->resolveTable($this->table, $adapter->getPlatform(), $adapter->getDriver()),
            implode(',', array_map([$adapter->getPlatform(), 'quoteIdentifier'], $this->columnNames)),
            str_repeat(
                sprintf('%s, ', $rowTemplate),
                $rowsCount - 1
            ),
            $rowTemplate
        );

        if (!$this->onDuplicate) {
            return $statement;
        }

        $onDuplicateStatements = [];

        foreach ($this->onDuplicate as $columnName) {
            $columnName = $adapter->getPlatform()->quoteIdentifier($columnName);
            $onDuplicateStatements[] = sprintf('%1$s = VALUES(%1$s)', $columnName);
        }

        return sprintf('%s ON DUPLICATE KEY UPDATE %s', $statement, implode(', ', $onDuplicateStatements));
    }

    /**
     * @param $limit
     *
     */
    private function executeResolvedValues($limit, AdapterInterface $adapter): void
    {
        while ($this->resolvedCount >= $limit) {
            $paramCount = $limit * count($this->columnNames);
            $parameters = array_slice($this->resolvedParams, 0, $paramCount);
            $this->resolvedParams = array_slice($this->resolvedParams, $paramCount);
            $this->resolvedCount -= $limit;
            $adapter->getDriver()->createStatement('SET FOREIGN_KEY_CHECKS=0')->execute();
            $this->limitStatement($limit, $adapter)->execute($parameters);
            $adapter->getDriver()->createStatement('SET FOREIGN_KEY_CHECKS=1')->execute();
        }
    }
}
