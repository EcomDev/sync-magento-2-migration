<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use League\CLImate\CLImate;
use League\CLImate\Exceptions\InvalidArgumentException;

class ImportApplication
{
    /**
     * @var DbFactory
     */
    private $dbFactory;
    /**
     * @var ImportFactory
     */
    private $importFactory;

    public function __construct(ImportFactory $importFactory, DbFactory $factory)
    {
        $this->dbFactory = $factory;
        $this->importFactory = $importFactory;
    }

    public static function create()
    {
        return new self(new ImportFactory(), new DbFactory());
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

            if (!is_dir($path)) {
                throw new InvalidArgumentException(
                    sprintf('Import Data directory does not exists "%s" [target_path]', $path)
                );
            }

            $import = $this->importFactory->create($path, $adapter);

            $import->importAttributes();
            $import->importCategories();
            $import->importProducts();
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

        $cli->arguments->add('mysql_db', [
            'description' => 'Database Name',
            'required' => true
        ]);

        $cli->arguments->add('target_path', [
            'description' => 'Import Data Directory',
            'required' => true
        ]);
    }
}
