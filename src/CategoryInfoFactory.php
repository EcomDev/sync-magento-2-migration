<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\MetadataInterface;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Sql;

class CategoryInfoFactory
{
    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;

    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var string[]
     */
    private $storeFilter = [];

    /** @var string[] */
    private $storeMap = [];
    /**
     * @var MetadataInterface
     */
    private $metadata;

    public function __construct(MagentoEavInfo $eavInfo, Sql $sql, MetadataInterface $metadata)
    {

        $this->eavInfo = $eavInfo;
        $this->sql = $sql;
        $this->metadata = $metadata;
    }

    public static function createFromAdapter(Adapter $adapter): self
    {
        return new self(MagentoEavInfo::createFromAdapter($adapter), new Sql($adapter), Factory::createSourceFromAdapter($adapter));
    }


    public function create(): CategoryInfo
    {
        return new CategoryInfo($this->eavInfo, $this->sql, $this->metadata, $this->storeFilter, $this->storeMap);
    }

    public function withStoreFilter(array $storeCodes): self
    {
        $factory = clone $this;
        $factory->storeFilter = $storeCodes;
        return $factory;
    }

    public function withStoreMap(array $storeCodes): self
    {
        $factory = clone $this;
        $factory->storeMap = $storeCodes;

        return $factory;
    }
}
