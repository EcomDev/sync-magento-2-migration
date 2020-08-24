<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use League\Csv\Reader;
use League\Csv\RFC4180Field;
use League\Csv\Writer;
use Symfony\Component\Finder\SplFileInfo;

class CsvFactory
{
    /** @var string */
    private $currentDirectory;

    /** @var array[] */
    private $skipFilters = [];

    /** @var array[] */
    private $mappings = [];

    /**
     * @var CsvReader
     */
    private $csvReader;

    public function __construct(string $currentDirectory)
    {
        $this->currentDirectory = $currentDirectory;
        $this->csvReader = new CsvReader();
    }

    public function createNativeWriter(string $fileName): Writer
    {
        $writer = Writer::createFromPath($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName, 'w');

        return $writer;
    }

    public function createWriter(string $fileName, array $headers): CsvWriter
    {
        $csvFile = new \SplFileObject($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName, 'w');
        $csvFile->setCsvControl(',', '"', "\0");
        $csvFile->fputcsv($headers);

        return new CsvWriter(
            $csvFile,
            $headers,
            $this->skipFilters[$fileName] ?? [],
            $this->mappings[$fileName] ?? []
        );
    }

    public function createReader(string $fileName): iterable
    {
        return $this->csvReader->readFile($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName);
    }

    public function withSkip(string $fileName, array $condition): self
    {
        $factory = clone $this;
        $factory->skipFilters[$fileName][] = $condition;
        return $factory;
    }

    public function withMap(string $fileName, string $field, array $values): self
    {
        $factory = clone $this;

        $factory->mappings[$fileName][$field] = $values;
        return $factory;
    }
}
