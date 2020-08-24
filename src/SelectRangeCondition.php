<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Zend\Db\Sql\Where;

class SelectRangeCondition implements SelectCondition
{
    /**
     * @var int
     */
    private $from;

    /**
     * @var int
     */
    private $to;

    public function __construct(int $from, int $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function apply(string $field, Where $where): void
    {
        $where->greaterThanOrEqualTo($field, $this->from);
        $where->lessThanOrEqualTo($field, $this->to);
    }
}
