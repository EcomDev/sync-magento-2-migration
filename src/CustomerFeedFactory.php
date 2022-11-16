<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

class CustomerFeedFactory implements FeedFactory
{
    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;

    /**
     * @var RowMapper[]
     */
    private $rowMappers = [];

    public function __construct(Sql $sql, MagentoEavInfo $eavInfo)
    {
        $this->sql = $sql;
        $this->eavInfo = $eavInfo;
    }

    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(
            new Sql($adapter),
            MagentoEavInfo::createFromAdapter($adapter)
        );
    }

    /**
     * @return CustomerFeed
     */
    public function create(): Feed
    {
        return new CustomerFeed($this->sql, $this->eavInfo, $this->rowMappers);
    }

    /**
     * @return $this
     */
    public function withRowMapper(string $feedCode, RowMapper $rowMapper): FeedFactory
    {
        $factory = clone $this;

        $piped = $factory->rowMappers[$feedCode] ?? new CombinedRowMapper();
        $factory->rowMappers[$feedCode] = $piped->pipe($rowMapper);

        return $factory;
    }
}
