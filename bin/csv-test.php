<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);


require __DIR__ . '/../vendor/autoload.php';

$factory = new \EcomDev\MagentoMigration\CsvFactory(getcwd());
$writer = $factory->createWriter('product_data_another.csv', ['sku', 'attribute', 'store', 'value']);
foreach ($factory->createReader('product_data.csv') as $row) {
    $writer->write($row);
}
