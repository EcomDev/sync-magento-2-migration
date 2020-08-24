<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class SelectConditionFactory
{
    public function in(array $values): SelectInCondition
    {
        return new SelectInCondition($values);
    }

    public function range(int $from, int $to): SelectRangeCondition
    {
        return new SelectRangeCondition($from, $to);
    }
}
