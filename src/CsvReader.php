<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class CsvReader
{
    public function readFile(string $fileName): iterable
    {
        if (!file_exists($fileName)) {
            return;
        }

        $reader = new \SplFileObject($fileName, 'r');

        $headers = $reader->fgetcsv(',', '"', "\0");

        while ($row = $reader->fgetcsv(',', '"', "\0")) {
            if (count($row) < count($headers)) {
                continue;
            }

            $item =  array_combine($headers, $row);
            yield $item;
        }
    }
}
