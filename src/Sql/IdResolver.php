<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\Sql;


interface IdResolver
{
    /**
     * @throws IdentifierNotResolved when value does not exists and cannot be generated
     */
    public function resolve(Identifier $value): int;

    /**
     * Creates a new resolvable id
     */
    public function unresolved($value): Identifier;
}
