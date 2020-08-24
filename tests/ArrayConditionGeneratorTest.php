<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use PHPUnit\Framework\TestCase;

class ArrayConditionGeneratorTest extends TestCase
{
    /** @test */
    public function returnsEmptyGeneratorWhenNoConditionsProvided()
    {
        $this->assertEquals(
            [],
            iterator_to_array(ArrayConditionGenerator::create()->conditions())
        );
    }

    /** @test */
    public function returnsProvidedConditions()
    {
        $conditionFactory = new SelectConditionFactory();

        $this->assertEquals(
            [
                $conditionFactory->range(1, 100),
                $conditionFactory->range(1, 200),
            ],
            iterator_to_array(ArrayConditionGenerator::create(
                $conditionFactory->range(1, 100),
                $conditionFactory->range(1, 200)
            )->conditions())
        );
    }
}
