<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use phpDocumentor\Reflection\Types\Boolean;

class CsvWriter
{
    /**
     * @var SplFileObject
     */
    private $writer;

    /**
     * @var string[]
     */
    private $headers;
    /**
     * @var array
     */
    private $skipConditions;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @var Boolean
     */
    private $encodeData;

    public function __construct(\SplFileObject $writer, array $headers, array $skipConditions, array $mapping, bool $encodeData = false)
    {
        $this->writer = $writer;
        $this->headers = $headers;
        $this->skipConditions = $skipConditions;
        $this->mapping = $mapping;
        $this->encodeData = $encodeData;
    }

    public function write(array $row): void
    {
        $csvRow = [];

        foreach ($this->skipConditions as $condition) {
            $isIgnored = true;
            foreach ($condition as $code => $ignoredValues) {
                $isReverse = strpos($code, '!') === 0;
                if ($isReverse) {
                    $code = substr($code, 1);
                }
                $value = $row[$code] ?? '';
                $isValueMatch = in_array($value, $ignoredValues);

                if ($isReverse) {
                    $isValueMatch = !$isValueMatch;
                }

                $isIgnored = $isIgnored && $isValueMatch;
            }

            if ($isIgnored) {
                return;
            }
        }

        foreach ($this->headers as $name) {
            $value = $row[$name] ?? '';

            if (isset($this->mapping[$name][$value])) {
                $value = $this->mapping[$name][$value];
            }

            $csvRow[] = (string)$value;
        }
        if ($this->encodeData) {
            $csvRow = array_map('base64_encode', $csvRow);
        }
        $this->writer->fputcsv($csvRow);
    }
}
