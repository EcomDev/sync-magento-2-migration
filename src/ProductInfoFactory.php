<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Metadata\MetadataInterface;
use Zend\Db\Metadata\Source\Factory;
use Zend\Db\Sql\Sql;

class ProductInfoFactory implements FeedFactory
{
    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;

    /**
     * @var Sql
     */
    private $sql;

    /** @var string[] */
    private $storeMap = [];

    /** @var string[] */
    private $ignoredAttributes = [];

    /** @var CombinedRowMapper[] */
    private $rowMappers = [];

    public function __construct(MagentoEavInfo $eavInfo, Sql $sql)
    {
        $this->eavInfo = $eavInfo;
        $this->sql = $sql;
    }

    public static function createFromAdapter(Adapter $adapter): self
    {
        return new self(
            MagentoEavInfo::createFromAdapter($adapter),
            new Sql($adapter),
            Factory::createSourceFromAdapter($adapter)
        );
    }

    /** @return ProductInfo */
    public function create(): Feed
    {
        return new ProductInfo($this->eavInfo, $this->sql, $this->storeMap, $this->ignoredAttributes, $this->rowMappers);
    }

    public function withStoreMap(array $storeMap): self
    {
        $factory = clone $this;
        $factory->storeMap = $storeMap;
        return $factory;
    }

    public function withIgnoredAttributes(array $attributeCodes)
    {
        $factory = clone $this;
        $factory->ignoredAttributes = $attributeCodes;

        return $factory;
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
