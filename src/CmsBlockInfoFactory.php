<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;



class CmsBlockInfoFactory
{
    public function withRowMapper(string $feedCode, RowMapper $rowMapper): self;
    public function create(): CmsBlockInfo;
}