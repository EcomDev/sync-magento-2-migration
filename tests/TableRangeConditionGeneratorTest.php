<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use PHPUnit\Framework\TestCase;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Sql;

class TableRangeConditionGeneratorTest extends TestCase
{
    /** @var Adapter */
    private static $testDb;

    /** @var TableRangeConditionGeneratorFactory */
    private $factory;
    /**
     * @var SelectConditionFactory
     */
    private $conditionFactory;

    public static function setUpBeforeClass()
    {
        $connection = (new TestDb())->createMagentoOneConnection();
        self::$testDb = $connection;
        $sql = new Sql($connection);

        $tables = [
            'sample_ranged_table_10k' => 10001,
            'sample_ranged_table_5k' => 5123,
            'sample_ranged_table_2k' => 2000
        ];

        foreach ($tables as $tableName => $maxValue) {
            $table = new CreateTable($tableName, true);
            $table->addColumn(new Integer('entity_id'))
                ->addConstraint(new PrimaryKey(['entity_id']));

            $connection->query($sql->buildSqlString($table))->execute();
            $insert = InsertOnDuplicate::create($tableName, ['entity_id']);

            foreach (range(1, $maxValue) as $id) {
                $insert->values(['entity_id' => $id], InsertOnDuplicate::VALUES_MERGE);
                $insert = $insert->flushIfLimitReached($sql);
            }

            $insert->executeIfNotEmpty($sql);
        }


    }

    public static function tearDownAfterClass()
    {
        self::$testDb = null;
    }

    protected function setUp()
    {
        $this->factory = TableRangeConditionGeneratorFactory::createFromAdapter(self::$testDb);
        $this->conditionFactory = new SelectConditionFactory();
    }

    /** @test */
    public function generatesConditions2KTable()
    {
        $generator = $this->factory->createForTable('sample_ranged_table_2k', 'entity_id');

        $this->assertEquals(
            [$this->conditionFactory->range(1, 2000)],
            iterator_to_array($generator->conditions())
        );
    }

    /** @test */
    public function generatesConditionsFor5KTable()
    {
        $generator = $this->factory->createForTable('sample_ranged_table_5k', 'entity_id');

        $this->assertEquals(
            [
                $this->conditionFactory->range(1, 2000),
                $this->conditionFactory->range(2001, 4000),
                $this->conditionFactory->range(4001, 5123),
            ],
            iterator_to_array($generator->conditions())
        );
    }

    /** @test */
    public function generatesConditionsFor10KTable()
    {
        $generator = $this->factory->createForTable('sample_ranged_table_10k', 'entity_id');

        $this->assertEquals(
            [
                $this->conditionFactory->range(1, 2000),
                $this->conditionFactory->range(2001, 4000),
                $this->conditionFactory->range(4001, 6000),
                $this->conditionFactory->range(6001, 8000),
                $this->conditionFactory->range(8001, 10000),
                $this->conditionFactory->range(10001, 10001),
            ],
            iterator_to_array($generator->conditions())
        );
    }
}
