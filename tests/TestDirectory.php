<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class TestDirectory
{
    private $path;

    public function __construct(string $path)
    {
        $this->path = $path;
    }

    public static function create(): self
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('test_directory');
        mkdir($directory, 0777, true);

        return new self($directory);
    }

    public function copyFrom(string $path)
    {
        foreach ($this->createDirectoryIterator($path, \RecursiveIteratorIterator::SELF_FIRST) as $item) {
            $relativePath = substr($item->getPathname(), strlen($path) + 1);
            if ($item->isDir()) {
                mkdir($this->resolvePath($relativePath), 0777, true);
                continue;
            }

            copy($item->getPathname(), $this->resolvePath($relativePath));
        }
    }

    public function resolvePath(string $path): string
    {
        return $this->path . ($path ?  DIRECTORY_SEPARATOR . $path : '');
    }

    public function __destruct()
    {
        if (!is_dir($this->path)) {
            return;
        }

        // Remove all temporary files and directories
        foreach ($this->listAllItems() as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
                continue;
            }

            unlink($item->getRealPath());
        }

        rmdir($this->path);
    }


    /**
     * Lists all entries in the directory
     * @return \SplFileInfo[]
     */
    public function listAllItems(): \Traversable
    {
        return $this->createDirectoryIterator($this->path, \RecursiveIteratorIterator::CHILD_FIRST);
    }

    /**
     *
     * @return \SplFileInfo[]
     */
    private function createDirectoryIterator(string $path, int $mode): \Traversable
    {
        return new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            $mode
        );
    }
}
