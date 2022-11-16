<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;
use Laminas\Db\Sql\Where;

class SelectConditionTest extends TestCase
{
    /** @var SelectConditionFactory */
    private $factory;

    protected function setUp()
    {
        $this->factory = new SelectConditionFactory();
    }

    /** @test */
    public function appliesImpossibleConditionWhenEmptyListOfValuesProvided()
    {
        $condition = $this->factory->in([]);

        $where = new Where();
        $condition->apply('some_field', $where);

        $this->assertEquals(
            (new Where())
                ->equalTo('some_field', null)
                ->notEqualTo('some_field', null),
            $where
        );
    }
    
    /** @test */
    public function appliesListOfValuesAsInConditionOnWhereInstance()
    {
        $condition = $this->factory->in([1, 2, 3]);


        $where = new Where();
        $condition->apply('field_name', $where);

        $this->assertEquals(
            (new Where())
                ->in('field_name', [1, 2, 3]),
            $where
        );
    }

    /**
     * @test
     * @testWith [1, 1000]
     * [1000, 2000]
     * [400, 1567]
     */
    public function appliesRangeConditionIntoWhere(int $startRange, int $endRange)
    {
        $condition = $this->factory->range($startRange, $endRange);

        $where = new Where();
        $condition->apply('field', $where);

        $this->assertEquals(
            (new Where())
                ->greaterThanOrEqualTo('field', $startRange)
                ->lessThanOrEqualTo('field', $endRange),
            $where
        );
    }
}
