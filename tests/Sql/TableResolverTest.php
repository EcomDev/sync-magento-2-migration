<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;


use EcomDev\MagentoMigration\TestDb;
use PHPUnit\Framework\TestCase;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\Index\Index;
use Laminas\Db\Sql\Sql;

class TableResolverTest extends TestCase
{
    /** @var TableResolverFactory */
    private $resolverFactory;

    /** @var Sql */
    private $sql;

    protected function setUp()
    {
        $connection = (new TestDb())
            ->createMagentoTwoConnection();

        $this->resolverFactory = TableResolverFactory::createFromAdapter($connection);

        $this->loadTableFixtures($connection);
    }
    
    /** @test */
    public function resolvesExistingValue()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $this->assertEquals(
            [1, 3, 4],
            array_map(
                function (Identifier $id) use ($resolver) {
                    return $resolver->resolve($id);
                },
                [
                    $resolver->unresolved('value1'),
                    $resolver->unresolved('value3'),
                    $resolver->unresolved('value4')
                ]
            )
        );
    }

    /** @test */
    public function resolvesNumericValues()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('table_with_numeric_text', 'numeric_text', 'id');

        $this->assertEquals(
            [1, 2, 3],
            array_map(
                function (Identifier $id) use ($resolver) {
                    return $resolver->resolve($id);
                },
                [
                    $resolver->unresolved('000001'),
                    $resolver->unresolved('123'),
                    $resolver->unresolved('123-123')
                ]
            )
        );
    }

    /** @test */
    public function doesNotBreakAfterResolve()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $unresolved =  $resolver->unresolved('value1');
        $resolver->resolve($unresolved);

        $second = $resolver->unresolved('value1');
        unset($unresolved);

        $this->assertEquals(1, $resolver->resolve($second));
    }

    /** @test */
    public function reusesUnresolvedYetValues()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $this->assertSame($resolver->unresolved('value1'), $resolver->unresolved('value1'));
    }
    
    /** @test */
    public function unresolvedValuesResultInException()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $valueOne = $resolver->unresolved('value1');
        $valueTwo = $resolver->unresolved('value2');

        $resolver->resolve($valueOne);

        $this->expectException(IdentifierNotResolved::class);

        $resolver->resolve($valueTwo);
    }
    
    /** @test */
    public function insertOnDuplicateSupportsResolverAndSkipsItemsThatAreUnResolvable()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $insert = InsertOnDuplicate::create('another_table', ['other_id', 'second_other'])
            ->withResolver($resolver)
            ->withRow($resolver->unresolved('value1'), 'other=value1')
            ->withRow($resolver->unresolved('value2'), 'other=value2')
            ->withRow($resolver->unresolved('value3'), 'other=value3')
            ->withRow($resolver->unresolved('value4'), 'other=value4')
        ;

        $insert->executeIfNotEmpty($this->sql);

        $this->assertEquals(
            [
                ['id' => '1', 'other_id' => '1', 'second_other' => 'value1'],
                ['id' => '2', 'other_id' => '3', 'second_other' => 'value1'],
                ['id' => '4', 'other_id' => '1', 'second_other' => 'value2'],
                ['id' => '8', 'other_id' => '4', 'second_other' => 'value4'],
                ['id' => '9', 'other_id' => '4', 'second_other' => 'value5'],
                ['id' => '10', 'other_id' => '1', 'second_other' => 'other=value1'],
                ['id' => '11', 'other_id' => '3', 'second_other' => 'other=value3'],
                ['id' => '12', 'other_id' => '4', 'second_other' => 'other=value4'],

            ],
            $this->fetchTableData('another_table')
        );
    }

    /** @test */
    public function insertOnDuplicateWorksWellWithMutlipleValuesResoves()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $insert = InsertOnDuplicate::create('another_table', ['other_id', 'second_other'])
            ->withResolver($resolver)
            ->withRow($resolver->unresolved('value1'), 'other=value1.1')
            ->withRow($resolver->unresolved('value3'), 'other=value3.1')
            ->withRow($resolver->unresolved('value4'), 'other=value4.1')
            ->flushIfLimitReached($this->sql, 3)
            ->withRow($resolver->unresolved('value1'), 'other=value1.2')
            ->withRow($resolver->unresolved('value3'), 'other=value3.2')
            ->withRow($resolver->unresolved('value4'), 'other=value4.2')
            ->flushIfLimitReached($this->sql, 3)
            ->withRow($resolver->unresolved('value1'), 'other=value1.3')
            ->withRow($resolver->unresolved('value3'), 'other=value3.3')
            ->withRow($resolver->unresolved('value4'), 'other=value4.3')
        ;

        $insert->executeIfNotEmpty($this->sql);

        $this->assertEquals(
            [
                ['id' => '1', 'other_id' => '1', 'second_other' => 'value1'],
                ['id' => '2', 'other_id' => '3', 'second_other' => 'value1'],
                ['id' => '4', 'other_id' => '1', 'second_other' => 'value2'],
                ['id' => '8', 'other_id' => '4', 'second_other' => 'value4'],
                ['id' => '9', 'other_id' => '4', 'second_other' => 'value5'],
                ['id' => '10', 'other_id' => '1', 'second_other' => 'other=value1.1'],
                ['id' => '11', 'other_id' => '3', 'second_other' => 'other=value3.1'],
                ['id' => '12', 'other_id' => '4', 'second_other' => 'other=value4.1'],
                ['id' => '13', 'other_id' => '1', 'second_other' => 'other=value1.2'],
                ['id' => '14', 'other_id' => '3', 'second_other' => 'other=value3.2'],
                ['id' => '15', 'other_id' => '4', 'second_other' => 'other=value4.2'],
                ['id' => '16', 'other_id' => '1', 'second_other' => 'other=value1.3'],
                ['id' => '17', 'other_id' => '3', 'second_other' => 'other=value3.3'],
                ['id' => '18', 'other_id' => '4', 'second_other' => 'other=value4.3'],
            ],
            $this->fetchTableData('another_table')
        );
    }

    /** @test */
    public function allowsToInsertMultiTableReferenceForResolver()
    {
        $simpleResolver = $this->resolverFactory->createSingleValueResolver(
            'some_table', 'other', 'id'
        );

        $resolver = $this->resolverFactory->createCombinedValueResolver(
            'another_table', 'id',
            'second_other', 'other_id',
            $simpleResolver
        );

        $insert = InsertOnDuplicate::create('another_table', ['id', 'other_id', 'second_other'])
            ->withResolver($resolver)
            ->withResolver($simpleResolver)
            ->onDuplicate(['other_id', 'second_other']);
        $insert
            ->withRow(
                $resolver->unresolved(['value1', 'value1']),
                $simpleResolver->unresolved('value1'),
                'other=value1'
            )
            ->withRow(
                $resolver->unresolved(['value2', 'value5']),
                $simpleResolver->unresolved('value2'),
                'other=invalid'
            )
            ->withRow(
                $resolver->unresolved(['value3', 'value3']),
                $simpleResolver->unresolved('value3'),
                'other=value5-3'
            )
        ;

        $insert->executeIfNotEmpty($this->sql);

        $this->assertEquals(
            [
                ['id' => '1', 'other_id' => '1', 'second_other' => 'other=value1'],
                ['id' => '2', 'other_id' => '3', 'second_other' => 'value1'],
                ['id' => '4', 'other_id' => '1', 'second_other' => 'value2'],
                ['id' => '8', 'other_id' => '4', 'second_other' => 'value4'],
                ['id' => '9', 'other_id' => '4', 'second_other' => 'value5'],
                ['id' => '10', 'other_id' => '3', 'second_other' => 'other=value5-3']
            ],
            $this->fetchTableData('another_table')
        );
    }

    /** @test */
    public function multiTableResolverDoesNotBreakAfterOriginalOneIsLost()
    {
        $simpleResolver = $this->resolverFactory->createSingleValueResolver(
            'some_table',
            'other',
            'id'
        );

        $resolver = $this->resolverFactory->createCombinedValueResolver(
            'another_table',
            'id',
            'second_other',
            'other_id',
            $simpleResolver
        );

        $value = $resolver->unresolved(['value1', 'value1']);
        $resolver->resolve($value);
        $resolvedInstance = $resolver->unresolved(['value1', 'value1']);
        unset($value);

        $this->assertEquals(1, $resolver->resolve($resolvedInstance));
    }

    /** @test */
    public function allowsToCreateAutoincrementedSingleTableResolver()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver(
            'some_table',
            'other',
            'id'
        )->withAutoIncrement(['other' => 'value']);

        $valueOne = $resolver->unresolved('value1');
        $valueTwo = $resolver->unresolved('value2');

        $this->assertEquals(
            [1, 10],
            [$resolver->resolve($valueOne), $resolver->resolve($valueTwo)]
        );
    }

    /** @test */
    public function allowsToSpecifyFiltersForSingleValueResolver()
    {
        $resolver = $this->resolverFactory
            ->createSingleValueResolver(
                'some_table',
                'other',
                'id'
            )
            ->withFilter(['id' => [1, 3]]);

        $valueOne = $resolver->unresolved('value1');
        $valueThree = $resolver->unresolved('value3');
        $valueFour = $resolver->unresolved('value4');

        $resolver->resolve($valueOne);
        $resolver->resolve($valueThree);

        $this->expectException(IdentifierNotResolved::class);

        $resolver->resolve($valueFour);
    }


    /**
     * @test
     */
    public function insertOnDuplicateAllowsToCreateFormattedFieldValues()
    {
        $resolver = $this->resolverFactory->createSingleValueResolver('some_table', 'other', 'id');

        $insert = InsertOnDuplicate::create('another_table', ['other_id', 'second_other'])
            ->withResolver($resolver)
            ->withFormatted('second_other', 'other=%s')
            ->withRow($resolver->unresolved('value1'), $resolver->unresolved('value1'))
            ->withRow($resolver->unresolved('value3'), 'value3')
            ->withRow($resolver->unresolved('value4'), $resolver->unresolved('value4'))
        ;

        $insert->executeIfNotEmpty($this->sql);

        $this->assertEquals(
            [
                ['id' => '1', 'other_id' => '1', 'second_other' => 'value1'],
                ['id' => '2', 'other_id' => '3', 'second_other' => 'value1'],
                ['id' => '4', 'other_id' => '1', 'second_other' => 'value2'],
                ['id' => '8', 'other_id' => '4', 'second_other' => 'value4'],
                ['id' => '9', 'other_id' => '4', 'second_other' => 'value5'],
                ['id' => '10', 'other_id' => '1', 'second_other' => 'other=1'],
                ['id' => '11', 'other_id' => '3', 'second_other' => 'other=value3'],
                ['id' => '12', 'other_id' => '4', 'second_other' => 'other=4'],

            ],
            $this->fetchTableData('another_table')
        );
    }

    /**
     * @param \Laminas\Db\Adapter\Adapter $connection
     *
     */
    protected function loadTableFixtures(\Laminas\Db\Adapter\Adapter $connection): void
    {
        $connection->createStatement(sprintf('DROP TEMPORARY TABLE IF EXISTS %s', 'some_table'));
        $connection->createStatement(sprintf('DROP TEMPORARY TABLE IF EXISTS %s', 'another_table'));

        $table = new CreateTable('some_table', true);
        $table->addColumn(new Integer('id', false, null, ['identity' => true]))
            ->addColumn(new Varchar('other', 255))
            ->addConstraint(new PrimaryKey('id'))
            ->addConstraint(new Index('other'))
        ;



        $this->sql = new Sql($connection);
        $connection->createStatement($this->sql->buildSqlString($table))->execute();

        $insert = InsertOnDuplicate::create('some_table', ['id', 'other'])
            ->withRow( 1, 'value1')
            ->withRow(3, 'value3')
            ->withRow(4, 'value4')
            ->withRow(8, 'value8')
            ->withRow( 9, 'value9')
        ;
        $insert->executeIfNotEmpty($this->sql);

        $table = new CreateTable('another_table', true);
        $table->addColumn(new Integer('id', false, null, ['identity' => true]))
            ->addColumn(new Integer('other_id'))
            ->addColumn(new Varchar('second_other', 255))
            ->addConstraint(new PrimaryKey('id'))
            ->addConstraint(new Index(['other_id', 'second_other']))
        ;

        $connection->createStatement($this->sql->buildSqlString($table))->execute();

        $insert = InsertOnDuplicate::create('another_table', ['id', 'other_id', 'second_other'])
            ->withRow(1, '1', 'value1')
            ->withRow(2, '3', 'value1')
            ->withRow(4, '1', 'value2')
            ->withRow(8, '4', 'value4')
            ->withRow(9, '4', 'value5')
        ;

        $insert->executeIfNotEmpty($this->sql);

        $table = new CreateTable('table_with_numeric_text', true);
        $table->addColumn(new Integer('id', false, null, ['identity' => true]))
            ->addColumn(new Varchar('numeric_text', 255))
            ->addConstraint(new PrimaryKey('id'))
        ;

        $connection->createStatement($this->sql->buildSqlString($table))->execute();

        $insert = InsertOnDuplicate::create('table_with_numeric_text', ['id', 'numeric_text'])
            ->withRow(1, '000001')
            ->withRow(2, '123')
            ->withRow(3, '123-123')
        ;

        $insert->executeIfNotEmpty($this->sql);
    }

    public function fetchTableData(string $tableName)
    {
        return iterator_to_array($this->sql->prepareStatementForSqlObject($this->sql->select($tableName)->order('id asc'))->execute());
    }
}
