<?php

/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;


use EcomDev\MagentoMigration\RowMapper;

class DescriptionMapper implements RowMapper
{
    public function apply(iterable $rows): iterable
    {
        foreach ($rows as $row) {

            if (in_array($row['attribute'], ['description', 'short_description'], true)) {
                $h4 = ['<h4>', '</h4>', '<p><strong>', '</strong></p>', '<p><b>', '</b></p>'];
                $h4new = ['<h3 itemprop="name">', '</h3>', '<h2>', '</h2>', '<h2>', '</h2>'];
                yield ['value' => str_replace($h4, $h4new, $row['value'])] + $row;
                continue;
            }

            yield $row;

        }
    }
}
