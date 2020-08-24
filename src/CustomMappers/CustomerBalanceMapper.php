<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;


use EcomDev\MagentoMigration\RowMapper;

class CustomerBalanceMapper implements RowMapper
{
    /** @var string */
    private $mergeSource = '';

    /** @var string */
    private $mergeTarget = '';

    /** @var float[] */
    private $rates = [];

    public function apply(iterable $rows): iterable
    {
        $mergeInfo = [];

        foreach ($rows as $row) {
            if ($row['website'] === $this->mergeSource) {
                $mergeInfo[$row['email']] = $mergeInfo[$row['email']] ?? 0;
                $mergeInfo[$row['email']] += $row['value'];
                continue;
            }

            if ($row['website'] === $this->mergeTarget && isset($mergeInfo[$row['email']])) {
                $row['value'] += $mergeInfo[$row['email']];
                unset($mergeInfo[$row['email']]);
            }

            $rate = $this->rates[$row['website']] ?? 0;

            if ($rate) {
                $row['value'] *= $rate;
            }

            yield $row;
        }
    }

    public function withMerge(string $source, string $target): self
    {
        $mapper = clone $this;
        $mapper->mergeSource = $source;
        $mapper->mergeTarget = $target;
        return $mapper;
    }

    public function withRate(string $websiteCode, float $rate): self
    {
        $mapper = clone $this;
        $mapper->rates[$websiteCode] = $rate;
        return $mapper;
    }
}
