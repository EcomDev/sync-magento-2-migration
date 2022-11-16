<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Ddl\CreateTable;

class TestDb
{
    /**
     * @var DbFactory
     */
    private $dbFactory;

    /**
     * @var string
     */
    private $snapshot;

    public function __construct(DbFactory $dbFactory = null)
    {
        $this->dbFactory = $dbFactory ?? new DbFactory();
    }

    public function createMagentoOneConnection(): Adapter
    {
        list($host, $user, $password) = $this->databaseConnectionSettings();

        return $this->dbFactory->createConnection(
            $host,
            $user,
            $password,
            $this->magentoOneDbName()
        );
    }

    public function createMagentoTwoConnection(): Adapter
    {

        list($host, $user, $password) = $this->databaseConnectionSettings();

        return $this->dbFactory->createConnection(
            $host,
            $user,
            $password,
            $this->magentoTwoDbName()
        );
    }

    public function resetMagentoTwoDb(): void
    {
        list($host, $user, $password) = $this->databaseConnectionSettings();

        $command = sprintf(
            'gunzip -c %s | mysql -u %s -p%s -h%s %s 2>/dev/null',
            __DIR__ . '/test.sql.gz',
            $user, $password, $host, $this->magentoTwoDbName()
        );

        shell_exec($command);

    }

    public function listTablesLike(string $pattern): array
    {
        list($host, $user, $password) = $this->databaseConnectionSettings();

        $tables = explode(
            PHP_EOL,
            shell_exec(sprintf('mysql -u %s -p%s -h%s %s -N -e "SHOW TABLES;" 2>/dev/null', $user, $password, $host, $this->magentoTwoDbName()))
        );

        return array_filter($tables, function ($table) use ($pattern) {
            return preg_match($pattern, $table);
        });
    }

    public function createSnapshot(array $tables): void
    {
        list($host, $user, $password) = $this->databaseConnectionSettings();

        $this->snapshot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('database-snapshot');

        $command = sprintf(
            'mysqldump -u %s -p%s -h%s %s %s 2>/dev/null | gzip -c > %s',
            $user, $password, $host, $this->magentoTwoDbName(), implode(' ', $tables), $this->snapshot
        );

        shell_exec($command);
    }

    public function restoreSnapshot(): void
    {
        if (!$this->snapshot) {
            return;
        }

        list($host, $user, $password) = $this->databaseConnectionSettings();

        $command = sprintf(
            'gunzip -c %s | mysql -u %s -p%s -h%s %s 2>/dev/null',
            $this->snapshot,
            $user, $password, $host, $this->magentoTwoDbName()
        );

        shell_exec($command);
    }

    private function databaseConnectionSettings()
    {
        return [
            (string)$_ENV['DB_HOST'],
            (string)$_ENV['DB_USER'],
            (string)$_ENV['DB_PASSWORD']
        ];
    }

    private function magentoTwoDbName(): string
    {
        return (string)$_ENV['DB_NAME_M2'];
    }

    private function magentoOneDbName(): string
    {
        return (string)$_ENV['DB_NAME'];
    }
}
