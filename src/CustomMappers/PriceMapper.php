<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;

use EcomDev\MagentoMigration\RowMapper;

class PriceMapper implements RowMapper
{
    /** @var float[] */
    private $rates = [];

    public function apply(iterable $rows): iterable
    {
        foreach ($rows as $row) {
            if ($row['attribute'] !== 'special_price') {
                yield $row;
            }
            if (in_array($row['attribute'], ['price', 'special_price'], true) && $row['store'] === '') {
                 foreach ($this->rates as $code => $rate) {
                     yield ['store' => $code, 'value' => $row['value']*$rate] + $row;
                 }
            }
        }
    }

    public function withStore(string $store, float $rate): self
    {
        $mapper = clone $this;
        $mapper->rates[$store] = $rate;
        return $mapper;
    }
}
