<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use League\CLImate\CLImate;
use League\CLImate\Exceptions\InvalidArgumentException;

class ExportApplication
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

            $adapter = $this->dbFactory->createConnection(
                $cli->arguments->get('mysql_host'),
                $cli->arguments->get('mysql_user'),
                $cli->arguments->get('mysql_password'),
                $cli->arguments->get('mysql_db')
            );

            $path = $cli->arguments->get('target_path');

            $configurationFile  = $cli->arguments->get('config');
            if ($configurationFile && file_exists($configurationFile)) {
                $configuration = json_decode(file_get_contents($configurationFile), true);
                $this->exportFactory = $this->exportFactory->withConfiguration((array)$configuration);
            }

            $export = $this->exportFactory->create($path, $adapter, (bool) $cli->arguments->get('encode_data'));

            if($cli->arguments->get('attributes_only')) {
                $export->exportAttributes();
            } else if($cli->arguments->get('products_data_only')) {
                $export->exportProductsDataOnly();
            } else {
                $export->exportAttributes();
                $export->exportCategories();
                $export->exportProducts();
                $export->exportCustomers();
            }
        } catch (InvalidArgumentException $e) {
            $cli->error($e->getMessage());
            $cli->usage();
        }

    }

    private function initializeArguments(CLImate $cli)
    {
        $cli->arguments->add('mysql_user', [
            'prefix' => 'u',
            'longPrefix' => 'db-user',
            'description' => 'Database User',
            'defaultValue' => get_current_user()
        ]);

        $cli->arguments->add('mysql_host', [
            'prefix' => 'h',
            'longPrefix' => 'db-host',
            'description' => 'Database Host',
            'defaultValue' => 'localhost'
        ]);

        $cli->arguments->add('mysql_password', [
            'prefix' => 'p',
            'longPrefix' => 'db-password',
            'description' => 'Database Password',
            'defaultValue' => ''
        ]);

        $cli->arguments->add('config', [
            'prefix' => 'c',
            'longPrefix' => 'configuration',
            'description' => 'JSON Configuration file',
            'defaultValue' => ''
        ]);

        $cli->arguments->add('mysql_db', [
            'description' => 'Database Name',
            'required' => true
        ]);

        $cli->arguments->add('target_path', [
            'description' => 'MagentoExport Directory',
            'required' => true
        ]);

        $cli->arguments->add('attributes_only', [
            'prefix' => 'a',
            'longPrefix' => 'attributes-only',
            'description' => 'Only export Attribute data',
            'defaultValue' => 0
        ]);

        $cli->arguments->add('products_data_only', [
            'prefix' => 'po',
            'longPrefix' => 'products-data-only',
            'description' => 'Only export product data',
            'defaultValue' => 0
        ]);

        $cli->arguments->add('encode_data', [
            'prefix' => 'ed',
            'longPrefix' => 'encode_data',
            'description' => 'Bae 64 encode data to csv file',
            'defaultValue' => 0
        ]);
    }
}
