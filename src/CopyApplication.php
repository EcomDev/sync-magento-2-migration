<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use League\CLImate\CLImate;
use League\CLImate\Exceptions\InvalidArgumentException;

class CopyApplication
{
    /**
     * @var MagentoExportFactory
     */
    private $exportFactory;
    /**
     * @var DbFactory
     */
    private $dbFactory;

    public function __construct(MagentoExportFactory $exportFactory, DbFactory $factory)
    {
        $this->exportFactory = $exportFactory;
        $this->dbFactory = $factory;
    }

    public static function create()
    {
        return new self(new MagentoExportFactory(), new DbFactory());
    }

    public function run(CLImate $cli)
    {
        $this->initializeArguments($cli);
        try {
            $cli->arguments->parse();

            $source = $cli->arguments->get('source_path');
            $path = $cli->arguments->get('target_path');

            $fileList = [
                'product.csv' => ['sku'],
                'product_category.csv' => ['sku'],
                'product_data.csv' => ['sku'],
                'product_image.csv' => ['sku', 'image'],
                'product_image_label.csv' => ['sku', 'image'],
                'product_website.csv' => ['sku'],
                'stock.csv' => ['sku']
            ];

            $prefixes = array_map(
                function () {
                    return uniqid('COPY_');
                },
                range(0, 10)
            );

            $targetFactory = new CsvFactory($path);
            $sourceFactory = new CsvFactory($source);

            foreach ($fileList as $fileName => $uniqueColumns) {
                $headers = [];
                foreach ($sourceFactory->createReader($fileName) as $row) {
                    $headers = array_keys($row);
                    break;
                }

                $this->copyFile(
                    $sourceFactory,
                    $targetFactory->createWriter($fileName, $headers),
                    $fileName,
                    $uniqueColumns,
                    $prefixes
                );
            }
        } catch (InvalidArgumentException $e) {
            $cli->error($e->getMessage());
            $cli->usage();
        }

    }

    private function initializeArguments(CLImate $cli)
    {
        $cli->arguments->add('source_path', [
            'description' => 'Import Directory',
            'required' => true
        ]);

        $cli->arguments->add('target_path', [
            'description' => 'MagentoExport Directory',
            'required' => true
        ]);
    }

    private function copyFile(CsvFactory $factory, CsvWriter $target, $fileName, array $uniqueFields, array $prefixes)
    {
        foreach ($prefixes as $prefix) {
            $reader = $factory->createReader($fileName);
            foreach ($reader as $row) {
                foreach ($uniqueFields as $field) {
                    $row[$field] = $prefix . $row[$field];
                }
                $target->write($row);
            }
        }
    }
}
