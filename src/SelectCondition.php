<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Laminas\Db\Sql\Where;

interface SelectCondition
{
    public function apply(string $field, Where $where): void;
}
