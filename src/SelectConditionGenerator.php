<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


/**
 * Generator for condition list based on the data in database
 *
 */
interface SelectConditionGenerator
{
    /**
     * @return SelectCondition[]
     */
    public function conditions(): \Generator;
}
