<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;

class UnresolvedIdentifier implements Identifier
{
    /**
     * @var callable
     */
    private $onDestroy;
    /**
     * @var string
     */
    private $value;

    public function __construct(string $value, callable $onDestroy)
    {
        $this->onDestroy = $onDestroy;
        $this->value = $value;
    }

    public function findValue(array $resolved): int
    {
        if (!isset($resolved[$this->value])) {
            throw new IdentifierNotResolved();
        }

        return $resolved[$this->value];
    }

    public function __destruct()
    {
        ($this->onDestroy)($this->value);
    }
}
