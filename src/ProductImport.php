<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use EcomDev\MagentoMigration\Sql\TableResolverFactory;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Ddl\Column\Integer;
use Zend\Db\Sql\Ddl\Constraint\PrimaryKey;
use Zend\Db\Sql\Ddl\CreateTable;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

class ProductImport
{
    /**
     * @var MagentoEavInfo
     */
    private $info;

    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var TableResolverFactory
     */
    private $resolverFactory;

    public function __construct(MagentoEavInfo $info, Sql $sql, TableResolverFactory $resolverFactory)
    {
        $this->info = $info;
        $this->sql = $sql;
        $this->resolverFactory = $resolverFactory;
    }


    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(
            MagentoEavInfo::createFromAdapter($adapter),
            new Sql($adapter),
            TableResolverFactory::createFromAdapter($adapter)
        );
    }

    public function importProducts(iterable $products): void
    {
        $attributeSetIds = array_flip($this->info->fetchAttributeSetMap('catalog_product'));

        $productResolver = $this->resolverFactory->createSingleValueResolver(
            'catalog_product_entity',
            'sku',
            'entity_id'
        );

        $insert = InsertOnDuplicate::create(
            'catalog_product_entity',
            ['entity_id', 'sku', 'type_id', 'attribute_set_id', 'has_options', 'required_options']
        )
            ->withResolver($productResolver)
            ->withNullOnUnresolved();

        $insert->onDuplicate(['type_id', 'attribute_set_id', 'has_options', 'required_options']);

        foreach ($products as $row) {
            if (!isset($attributeSetIds[$row['set']])) {
                continue;
            }

            $insert = $insert->withRow(
                $productResolver->unresolved($row['sku']),
                $row['sku'],
                $row['type'],
                $attributeSetIds[$row['set']],
                in_array($row['type'], ['configurable', 'bundle']),
                in_array($row['type'], ['configurable', 'bundle'])
            )->flushIfLimitReached($this->sql);
        }

        $insert->executeIfNotEmpty($this->sql);
    }

    private function transactional(callable $codeBlock)
    {
        $connection = $this->sql->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $codeBlock();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }

    public function importProductData(iterable $productData)
    {
        $this->transactional(function () use ($productData) {
            $varcharType = ['type' => 'varchar'];
            $attributes = [];
            foreach (['image', 'small_image', 'thumbnail'] as $attribute) {
                $attributes[$attribute] = $varcharType;
                $attributes[$attribute . '_label'] = $varcharType;
            }

            $attributes += $this->info->fetchProductAttributes();
            $attributeIds = $this->info->fetchAttributeIds('catalog_product', array_keys($attributes));

            $types = ['varchar', 'int', 'decimal', 'text', 'datetime'];

            $options = $this->info->fetchAttributeOptions('catalog_product', array_keys($attributes));
            $storeMap = $this->info->fetchStoreMap();

            foreach ($options as $attribute => $attributeOptions) {
                $options[$attribute] = array_flip($attributeOptions);
            }

            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            /** @var InsertOnDuplicate[] $typeInserts */
            $typeInserts = [];
            foreach ($types as $type) {
                $typeInserts[$type] = InsertOnDuplicate::create(
                    'catalog_product_entity_' . $type,
                    ['entity_id', 'attribute_id', 'store_id', 'value']
                )->withResolver($productResolver)->onDuplicate(['value']);
            }


            foreach ($productData as $row) {
                $type = $attributes[$row['attribute']]['type'] ?? '';

                if (!isset($typeInserts[$type])) {
                    continue;
                }

                $attributeOptions = $options[$row['attribute']] ?? [];

                $typeInserts[$type] = $typeInserts[$type]
                    ->withRow(
                        $productResolver->unresolved($row['sku']),
                        $attributeIds[$row['attribute']],
                        $storeMap[$row['store']] ?? 0,
                        $attributeOptions ? $this->resolveOption($row['value'], $attributeOptions) : $this->resolveEmptyValue($row['value'], $type)
                    )
                    ->flushIfLimitReached($this->sql);
            }

            foreach ($typeInserts as $insert) {
                $insert->executeIfNotEmpty($this->sql);
            }

            $select = $this->sql->select(['price' => 'catalog_product_entity_decimal'])
                ->join(['product' => 'catalog_product_entity'], 'product.entity_id = price.entity_id', [])
                ->columns(['value_id'])
                ->where([
                    'product.type_id' => 'configurable',
                    'price.attribute_id' => [
                        $attributeIds['price'],
                        $attributeIds['special_price']
                    ]
                ]);

            $table = new CreateTable('product_price_in_configurable', true);
            $table->addColumn(new Integer('value_id'))
                ->addConstraint(new PrimaryKey('value_id'));

            $this->sql->getAdapter()->getDriver()->createStatement(
                $this->sql->buildSqlString($table)
            )->execute();

            $this->sql->prepareStatementForSqlObject(
                $this->sql->insert('product_price_in_configurable')->values($select)
            )->execute();

            $this->sql->prepareStatementForSqlObject(
                $this->sql->delete('catalog_product_entity_decimal')
                    ->where(function (Where $where) {
                        $where->in(
                            'value_id',
                            $this->sql->select('product_price_in_configurable')->columns(['value_id'])
                        );
                    })
            )->execute();

            $this->sql->getAdapter()->getDriver()->createStatement('DROP TEMPORARY TABLE product_price_in_configurable')
                ->execute();
        });
    }

    private function resolveOption($value, array $attributeOptions)
    {
        if (!isset($attributeOptions[$value]) && strpos($value, ',') !== false) {
            return implode(',', array_filter(array_map(function ($value) use ($attributeOptions) {
                return $this->resolveOption($value, $attributeOptions);
            }, explode(',', $value))));
        }

        return $attributeOptions[$value] ?? '';
    }

    public function importProductWebsite(iterable $productWebsite)
    {
        $this->transactional(function () use ($productWebsite) {
            $websiteMap = $this->info->fetchWebsiteMap($this->info->fetchStoreMap());

            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            $insert = InsertOnDuplicate::create('catalog_product_website', ['product_id', 'website_id'])
                ->withResolver($productResolver)
                ->onDuplicate(['website_id']);

            foreach ($productWebsite as $row) {
                $insert = $insert->withRow($productResolver->unresolved($row['sku']), $websiteMap[$row['store']])
                    ->flushIfLimitReached($this->sql);
            }

            $insert->executeIfNotEmpty($this->sql);
        });
    }

    public function importProductCategory(iterable $productCategory)
    {
        $this->transactional(function () use ($productCategory) {
            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            $categoryResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_category_migration_m1_category_map',
                'foreign_id',
                'entity_id'
            );

            $insert = InsertOnDuplicate::create(
                'catalog_category_product',
                ['product_id', 'category_id', 'position']
            )
                ->withResolver($productResolver)
                ->withResolver($categoryResolver)
                ->onDuplicate(['position']);

            foreach ($productCategory as $row) {
                $insert = $insert
                    ->withRow(
                        $productResolver->unresolved($row['sku']),
                        $categoryResolver->unresolved($row['category']),
                        $row['position']
                    )
                    ->flushIfLimitReached($this->sql);
            }

            $insert->executeIfNotEmpty($this->sql);
        });
    }

    public function importStock(iterable $productStock)
    {
        $this->transactional(
            function () use ($productStock) {
                $productResolver = $this->resolverFactory->createSingleValueResolver(
                    'catalog_product_entity',
                    'sku',
                    'entity_id'
                );

                $insert = InsertOnDuplicate::create(
                    'cataloginventory_stock_item',
                    ['product_id', 'stock_id', 'qty', 'is_in_stock']
                )
                    ->withResolver($productResolver)
                    ->onDuplicate(['qty', 'is_in_stock'])
                ;

                foreach ($productStock as $row) {
                    $insert = $insert
                        ->withRow(
                            $productResolver->unresolved($row['sku']),
                            $row['stock'],
                            $row['qty'],
                            $row['in_stock']
                        )
                        ->flushIfLimitReached($this->sql)
                    ;
                }

                $insert->executeIfNotEmpty($this->sql);
            }
        );
    }

    public function importGallery(iterable $productImages)
    {
        $galleryAttributeId = current(
            $this->info->fetchAttributeIds('catalog_product', ['media_gallery'])
        );

        $productResolver = $this->resolverFactory->createSingleValueResolver(
            'catalog_product_entity',
            'sku',
            'entity_id'
        );

        $imageResolver = $this->resolverFactory->createSingleValueResolver(
            'catalog_product_entity_media_gallery',
            'value',
            'value_id'
        );

        $imageResolver = $imageResolver
            ->withAutoIncrement(['attribute_id' => $galleryAttributeId, 'media_type' => 'image']);

        $insertGallery = InsertOnDuplicate::create(
            'catalog_product_entity_media_gallery',
            ['value_id', 'attribute_id', 'value']
        )
            ->withResolver($imageResolver)
            ->onDuplicate(['value'])
        ;

        $insertGalleryEntity = InsertOnDuplicate::create(
            'catalog_product_entity_media_gallery_value_to_entity',
            ['value_id', 'entity_id']
        )
            ->withResolver($imageResolver)
            ->withResolver($productResolver)
            ->onDuplicate(['entity_id'])
        ;

        foreach ($productImages as $row) {
            $insertGallery = $insertGallery
                ->withRow(
                    $imageResolver->unresolved($row['image']),
                    $galleryAttributeId,
                    $row['image']
                )
            ;



            $insertGalleryEntity = $insertGalleryEntity
                ->withRow(
                    $imageResolver->unresolved($row['image']),
                    $productResolver->unresolved($row['sku'])
                )
            ;

            $insertGallery = $insertGallery->flushIfLimitReached($this->sql);
            $insertGalleryEntity = $insertGalleryEntity->flushIfLimitReached($this->sql);
        }

        $insertGallery->executeIfNotEmpty($this->sql);
        $insertGalleryEntity->executeIfNotEmpty($this->sql);
    }

    public function importGalleryValues(iterable $images)
    {
        $this->transactional(function () use ($images) {
            $imageResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity_media_gallery',
                'value',
                'value_id'
            );

            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            $insertGalleryValue = InsertOnDuplicate::create(
                'catalog_product_entity_media_gallery_value',
                ['value_id', 'entity_id', 'store_id', 'label', 'position']
            )
                ->withResolver($imageResolver)
                ->withResolver($productResolver)
                ->onDuplicate(['entity_id'])
            ;

            $storeMap = $this->info->fetchStoreMap();

            foreach ($images as $row) {
                $insertGalleryValue = $insertGalleryValue
                    ->withRow(
                        $imageResolver->unresolved($row['image']),
                        $productResolver->unresolved($row['sku']),
                        $storeMap[$row['store']] ?? 0,
                        $row['label'],
                        $row['position']
                    )->flushIfLimitReached($this->sql);
            }

            $insertGalleryValue->executeIfNotEmpty($this->sql);
        });
    }

    public function importProductUrls(iterable $urls)
    {
        $this->transactional(function () use ($urls) {
            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            $urlKeyAttributeId = current(
                $this->info->fetchAttributeIds('catalog_product', ['url_key'])
            );
            $storeMap = $this->info->fetchStoreMap();
            $defaultStore = key($storeMap);

            $urlInsert = InsertOnDuplicate::create(
                'url_rewrite',
                ['entity_type', 'entity_id', 'request_path', 'target_path', 'store_id', 'is_autogenerated']
            )
                ->withFormatted('target_path', 'catalog/product/view/id/%s')
                ->withResolver($productResolver)
                ->onDuplicate(['target_path', 'entity_type', 'entity_id', 'is_autogenerated'])
                ;

            $attributeInsert = InsertOnDuplicate::create(
                'catalog_product_entity_varchar',
                ['entity_id', 'attribute_id', 'store_id', 'value']
            )->withResolver($productResolver)->onDuplicate(['value']);

            foreach ($urls as $url) {
                if (!isset($storeMap[$url['store']])) {
                    continue;
                }

                $urlInsert = $urlInsert->withRow(
                    'product',
                    $productResolver->unresolved($url['sku']),
                    $url['url'] . '.html',
                    $productResolver->unresolved($url['sku']),
                    $storeMap[$url['store']],
                    1
                )->flushIfLimitReached($this->sql);

                if ($url['store'] === $defaultStore) {
                    $attributeInsert = $attributeInsert->withRow(
                        $productResolver->unresolved($url['sku']),
                        $urlKeyAttributeId,
                        0,
                        $url['url']
                    )->flushIfLimitReached($this->sql);
                }
            }

            $urlInsert->executeIfNotEmpty($this->sql);
            $attributeInsert->executeIfNotEmpty($this->sql);
        });
    }

    public function importProductConfigurableAttributes(iterable $productAttributes)
    {
        $optionAttributes = array_filter(
            iterator_to_array($this->info->fetchProductAttributeConfiguration(array_keys($this->info->fetchProductAttributes()))),
            function ($attribute) {
                return $attribute['option'];
            }
        );

        $optionAttributes = $this->info->fetchAttributeIds('catalog_product', array_keys($optionAttributes));

        $productResolver = $this->resolverFactory->createSingleValueResolver(
            'catalog_product_entity',
            'sku',
            'entity_id'
        );

        $attributeResolver = $this->resolverFactory
            ->createCombinedValueResolver(
                'catalog_product_super_attribute',
                'product_super_attribute_id',
                'attribute_id',
                'product_id',
                $productResolver
            );

        $attributeInsert = InsertOnDuplicate::create(
            'catalog_product_super_attribute',
            ['product_super_attribute_id', 'product_id', 'attribute_id', 'position']
        )
            ->withResolver($productResolver)
            ->withResolver($attributeResolver)
            ->onDuplicate(['position']);

        $attributeValueInsert = InsertOnDuplicate::create(
            'catalog_product_super_attribute_label',
            ['product_super_attribute_id', 'value']
        )
            ->withResolver($attributeResolver)
            ->onDuplicate(['value']);

        foreach ($productAttributes as $row) {
            if (!isset($optionAttributes[$row['attribute']])) {
                continue;
            }

            $attributeInsert = $attributeInsert->withRow(
                $attributeResolver->unresolved(
                    [$optionAttributes[$row['attribute']], $row['sku']]
                ),
                $productResolver->unresolved($row['sku']),
                $optionAttributes[$row['attribute']],
                $row['position']
            )->flushIfLimitReached($this->sql);

            $attributeValueInsert = $attributeValueInsert->withRow(
                $attributeResolver->unresolved(
                    [$optionAttributes[$row['attribute']], $row['sku']]
                ),
                $row['label']
            )->flushIfLimitReached($this->sql);
        }

        $attributeInsert->executeIfNotEmpty($this->sql);
        $attributeValueInsert->executeIfNotEmpty($this->sql);
    }

    public function importProductConfigurableRelation(iterable $configurableRelations)
    {
        $this->transactional(function () use ($configurableRelations) {
            $productResolver = $this->resolverFactory->createSingleValueResolver(
                'catalog_product_entity',
                'sku',
                'entity_id'
            );

            $linkInsert = InsertOnDuplicate::create(
                'catalog_product_super_link',
                ['parent_id', 'product_id']
            )
                ->withResolver($productResolver)
                ->onDuplicate(['product_id']);

            $relationInsert = InsertOnDuplicate::create(
                'catalog_product_relation',
                ['parent_id', 'child_id']
            )
                ->withResolver($productResolver)
                ->onDuplicate(['child_id']);

            foreach ($configurableRelations as $row) {
                $linkInsert = $linkInsert->withRow(
                    $productResolver->unresolved($row['sku']),
                    $productResolver->unresolved($row['child_sku'])
                )->flushIfLimitReached($this->sql);

                $relationInsert = $relationInsert->withRow(
                    $productResolver->unresolved($row['sku']),
                    $productResolver->unresolved($row['child_sku'])
                )->flushIfLimitReached($this->sql);
            }

            $linkInsert->executeIfNotEmpty($this->sql);
            $relationInsert->executeIfNotEmpty($this->sql);
        });
    }

    private function resolveEmptyValue(string $value, string $type)
    {
        return $type !== 'varchar' && $type !== 'text' && $value === '' ? null : $value;
    }
}
