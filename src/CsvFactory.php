<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use League\Csv\Writer;

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

    /**
     * @var bool
     */
    private $encodedData;

    /**
     * @param string $currentDirectory
     * @param bool $encodedData
     */
    public function __construct(
        string $currentDirectory,
        bool $encodedData = false
    ) {
        $this->currentDirectory = $currentDirectory;
        $this->encodedData = $encodedData;
        $this->csvReader = new CsvReader();
    }

    /**
     * @param string $fileName
     * @return Writer
     */
    public function createNativeWriter(string $fileName): Writer
    {
        return Writer::createFromPath($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName, 'w');
    }

    /**
     * @param string $fileName
     * @param array $headers
     * @return CsvWriter
     */
    public function createWriter(string $fileName, array $headers): CsvWriter
    {
        $csvFile = new \SplFileObject($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName, 'w');
        $csvFile->setCsvControl(',', '"', "\0");
        $csvFile->fputcsv($headers);

        return new CsvWriter(
            $csvFile,
            $headers,
            $this->skipFilters[$fileName] ?? [],
            $this->mappings[$fileName] ?? [],
            $this->encodedData
        );
    }

    /**
     * @param string $fileName
     * @return iterable
     */
    public function createReader(string $fileName): iterable
    {
        return $this->csvReader->readFile($this->currentDirectory . DIRECTORY_SEPARATOR . $fileName, $this->encodedData);
    }

    /**
     * @param string $fileName
     * @param array $condition
     * @return $this
     */
    public function withSkip(string $fileName, array $condition): self
    {
        $factory = clone $this;
        $factory->skipFilters[$fileName][] = $condition;
        return $factory;
    }

    /**
     * @param string $fileName
     * @param string $field
     * @param array $values
     * @return $this
     */
    public function withMap(string $fileName, string $field, array $values): self
    {
        $factory = clone $this;
        $factory->mappings[$fileName][$field] = $values;
        return $factory;
    }
}
