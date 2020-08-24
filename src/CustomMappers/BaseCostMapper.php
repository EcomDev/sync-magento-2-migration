<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;


use EcomDev\MagentoMigration\RowMapper;

class BaseCostMapper implements RowMapper
{
    /** @var float[] */
    private $stores = [];

    /** @var float[] */
    private $currencyConversion = [];

    public function apply(iterable $rows): iterable
    {
        $baseCost = [];

        $isFullBaseCost = function ($cost) {
            return isset($cost['value']) && isset($cost['currency']);
        };

        $generateBaseCost = function ($sku, $cost) {
            $costValue = $cost['value'] * ($this->currencyConversion[$cost['currency']] ?? 1);

            yield [
                'sku' => $sku,
                'attribute' => 'cost',
                'store' => '',
                'value' => $costValue
            ];

            foreach ($this->stores as $store => $rate) {
                yield [
                    'sku' => $sku,
                    'attribute' => 'cost',
                    'store' => $store,
                    'value' => $costValue * $rate
                ];
            }
        };

        foreach ($rows as $row) {
            if ($row['attribute'] === 'base_cost') {
                $baseCost[$row['sku']]['value'] = (float)$row['value'];
                if ($isFullBaseCost($baseCost[$row['sku']])) {
                    foreach ($generateBaseCost($row['sku'], $baseCost[$row['sku']]) as $costRow) {
                        yield $costRow;
                    }
                    unset($baseCost[$row['sku']]);
                }
                continue;
            } elseif ($row['attribute'] === 'base_cost_currency') {
                $baseCost[$row['sku']]['currency'] = $row['value'];
                if ($isFullBaseCost($baseCost[$row['sku']])) {
                    foreach ($generateBaseCost($row['sku'], $baseCost[$row['sku']]) as $costRow) {
                        yield $costRow;
                    }
                    unset($baseCost[$row['sku']]);
                }
                continue;
            }

            yield $row;
        }
    }

    public function withDefaultStore(string $storeCode): self
    {
        return $this->withStore($storeCode, 1);
    }

    public function withStore(string $store, $rate): self
    {
        $mapper = clone $this;
        $mapper->stores[$store] = (float)$rate;

        return $mapper;
    }

    public function withCurrencyConversion(string $currency, $rate): self
    {
        $mapper = clone $this;
        $mapper->currencyConversion[$currency] = (float)$rate;
        return $mapper;
    }
}
