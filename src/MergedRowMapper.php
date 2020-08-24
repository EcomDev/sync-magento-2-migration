<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class MergedRowMapper implements RowMapper
{
    /** @var iterable[] */
    private $csvFiles = [];

    /** @var CsvReader */
    private $csvReader;

    public function __construct(CsvReader $csvReader = null)
    {
        $this->csvReader = $csvReader ?? new CsvReader();
    }

    public function apply(iterable $rows): iterable
    {
        foreach ($rows as $row) {
            yield $row;
        }

        foreach ($this->csvFiles as $additionalRows) {
            foreach ($additionalRows as $row) {
                yield $row;
            }
        }
    }

    public function withCsvFile(string $fileName): self
    {
        $mapper = clone $this;
        $mapper->csvFiles[] = $this->csvReader->readFile($fileName);
        return $mapper;
    }
}
