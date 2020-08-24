<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class CombinedRowMapper implements RowMapper
{
    /** @var RowMapper[] */
    private $mappers = [];

    public function apply(iterable $rows): iterable
    {
        if (!$this->mappers) {
            return $rows;
        }

        foreach ($this->mappers as $mapper) {
            $rows = $mapper->apply($rows);
        }

        return $rows;

    }

    public function pipe(RowMapper $mapper): self
    {
        $piped = clone $this;
        $piped->mappers[] = $mapper;
        return $piped;
    }
}
