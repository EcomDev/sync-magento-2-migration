<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Laminas\Db\Adapter\Adapter;

class MagentoExportFactory
{
    private $configuration = [];

    public function create(string $targetDirectory, Adapter $adapter, bool $encodeData = false): MagentoExport
    {
        if (!is_dir($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        $csvFactory = new CsvFactory($targetDirectory, $encodeData);

        $eavInfo = MagentoEavInfo::createFromAdapter($adapter);

        foreach ($this->configuration as $fileName => $options) {
            if (!isset($options['skip']) && !isset($options['map'])) {
                continue;
            }

            $skip = $options['skip'] ?? [];
            foreach ($skip as $condition) {
                $csvFactory = $csvFactory->withSkip($fileName, $condition);
            }

            $map = $options['map'] ?? [];

            foreach ($map as $column => $values) {
                $csvFactory = $csvFactory->withMap($fileName, $column, $values);
            }
        }

        $categoryFactory = CategoryInfoFactory::createFromAdapter($adapter)
            ->withStoreFilter($this->configuration['category']['active_filter'] ?? [])
            ->withStoreMap($this->configuration['category']['stores'] ?? []);

        $productFactory = ProductInfoFactory::createFromAdapter($adapter)
            ->withStoreMap($this->configuration['category']['stores'] ?? [])
            ->withIgnoredAttributes($this->configuration['product']['ignored_attributes'] ?? []);


        $customerFactory = CustomerFeedFactory::createFromAdapter($adapter);

        /** @var FeedFactory[] $mapperTypes */
        $mapperTypes = [
            'product' => $productFactory,
            'customer' => $customerFactory
        ];

        foreach ($mapperTypes as $type => $feedFactory) {
            $mappers = $this->configuration[$type]['mappers'] ?? [];
            $mapperTypes[$type] = $this->applyMapperConfiguration($mappers, $feedFactory);
        }

        $productFactory = $mapperTypes['product'];
        $customerFactory = $mapperTypes['customer'];


        return new MagentoExport(
            new EavMetadataExport($eavInfo, $csvFactory),
            new CategoryExport($categoryFactory->create(), $csvFactory),
            new ProductExport(
                $productFactory->create(),
                TableRangeConditionGeneratorFactory::createFromAdapter($adapter)
                    ->createForTable('catalog_product_entity', 'entity_id'),
                $csvFactory
            ),
            new CustomerExport(
                $customerFactory->create(),
                TableRangeConditionGeneratorFactory::createFromAdapter($adapter)
                    ->createForTable('customer_entity', 'entity_id'),
                $csvFactory
            )
        );
    }

    public function withConfiguration(array $configuration): self
    {
        $factory = clone $this;
        $factory->configuration = $configuration;
        return $factory;
    }

    private function createMapper(array $mapperConfiguration): RowMapper
    {
        $mapperClass = $mapperConfiguration['class'];
        $mapper = new $mapperClass();
        $setup = $mapperConfiguration['setup'] ?? [];
        foreach ($setup as $call) {
            $method = array_shift($call);
            $mapper = $mapper->{$method}(...$call);
        }

        return $mapper;
    }

    /**
     * @return ProductInfoFactory|CustomerFeedFactory
     */
    protected function applyMapperConfiguration(array $mappers, FeedFactory $feedFactory): FeedFactory
    {
        foreach ($mappers as $mapperCode => $mapperList) {
            foreach ($mapperList as $mapperConfiguration) {
                $feedFactory = $feedFactory->withRowMapper(
                    $mapperCode,
                    $this->createMapper($mapperConfiguration)
                );
            }
        }

        return $feedFactory;
    }
}
