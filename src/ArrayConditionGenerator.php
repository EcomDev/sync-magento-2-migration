<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class ArrayConditionGenerator implements SelectConditionGenerator
{
    /**
     * @var array
     */
    private $conditions;

    public function __construct(array $conditions)
    {
        $this->conditions = $conditions;
    }

    public static function create(SelectCondition ...$conditions): self
    {
        return new self($conditions);
    }

    public function conditions(): \Generator
    {
        foreach ($this->conditions as $condition) {
            yield $condition;
        }
    }
}
