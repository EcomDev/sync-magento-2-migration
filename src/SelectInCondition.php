<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Laminas\Db\Sql\Where;

class SelectInCondition implements SelectCondition
{
    /** @var array */
    private $values;


    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function apply(string $field, Where $where): void
    {
        if (!$this->values) {
            $where->equalTo($field, null)
                ->notEqualTo($field, null);

            return;
        }

        $where->in($field, $this->values);
    }
}
