<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use Laminas\Db\Sql\Combine;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Having;
use Laminas\Db\Sql\Literal;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class ProductInfo implements Feed
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
    private $storeMap;

    /**
     * @var string[]
     */
    private $ignoredAttributes;

    /** @var RowMapper[] */
    private $rowMappers;

    public function __construct(
        MagentoEavInfo $eavInfo,
        Sql $sql,
        array $storeMap,
        array $ignoredAttributes,
        array $rowMappers
    ) {
        $this->eavInfo = $eavInfo;
        $this->sql = $sql;
        $this->storeMap = $storeMap;
        $this->ignoredAttributes = $ignoredAttributes;
        $this->rowMappers = $rowMappers;
    }

    public function fetchProducts(SelectConditionGenerator $conditionGenerator): iterable
    {
        $attributeSetNames = $this->eavInfo->fetchAttributeSetMap('catalog_product');

        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select()
                ->from(['product' => 'catalog_product_entity'])
                ->columns(['sku', 'type' => 'type_id', 'set' => 'attribute_set_id'])
                ->where(function (Where $where) use ($condition) {
                    $condition->apply('product.entity_id', $where);
                })
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield ['set' => $attributeSetNames[$row['set']]] + $row;
            }
        }
    }

    public function fetchProductAttributes(SelectConditionGenerator $conditionGenerator): iterable
    {
        return $this->applyMappers(
            'product_attributes',
            $this->generateProductAttributeData($conditionGenerator)
        );
    }

    /**
     * @param array $optionMap
     * @param $row
     *
     * @return mixed
     */
    private function resolveOptionId(array $optionMap, $row)
    {
        if (!isset($optionMap[$row['attribute_id']])) {
            return $row['value'];
        }

        $resolver = $this->resolveOptionByIdClosure($optionMap[$row['attribute_id']]);

        if (!$row['is_multiple']) {
            return $resolver($row['value']);
        }

        return implode(
            ',',
            array_filter(
                array_map($resolver, explode(',', $row['value'])),
                function ($v) {
                    return $v !== '';
                }
            )
        );
    }

    private function resolveOptionByIdClosure($options) {
        return function ($optionId) use ($options) {
            return $options[$optionId] ?? '';
        };
    }

    public function fetchProductWebsite(SelectConditionGenerator $conditionGenerator): iterable
    {
        $websiteMap = array_flip($this->eavInfo->fetchWebsiteMap($this->storeMap));

        if (!$websiteMap) {
            return;
        }

        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['website' => 'catalog_product_website'])
                ->columns(['website_id'])
                ->join(['product' => 'catalog_product_entity'], 'product.entity_id = website.product_id', ['sku'])
                ->where(function (Where $where) use ($condition, $websiteMap) {
                    $condition->apply('website.product_id', $where);
                    $where->in('website.website_id', array_keys($websiteMap));
                });

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield [
                    'sku' => $row['sku'],
                    'store' => $websiteMap[$row['website_id']]
                ];
            }
        }
    }

    public function fetchProductCategories(SelectConditionGenerator $conditionGenerator): iterable
    {
        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['category' => 'catalog_category_product'])
                ->columns(['category_id', 'position'])
                ->join(['product' => 'catalog_product_entity'], 'product.entity_id = category.product_id', ['sku'])
                ->where(function (Where $where) use ($condition) {
                    $condition->apply('category.product_id', $where);
                });

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield [
                    'sku' => $row['sku'],
                    'category' => 'category_' . $row['category_id'],
                    'position' => $row['position']
                ];
            }
        }
    }

    public function fetchProductStock(SelectConditionGenerator $conditionGenerator): iterable
    {
        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['stock' => 'cataloginventory_stock_item'])
                ->columns(['stock_id', 'qty', 'in_stock' => 'is_in_stock'])
                ->join(['product' => 'catalog_product_entity'], 'product.entity_id = stock.product_id', ['sku'])
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('stock.product_id', $where);
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield [
                    'sku' => $row['sku'],
                    'qty' => (float)$row['qty'],
                    'stock' => $row['stock_id'],
                    'in_stock' => $row['in_stock']
                ];
            }
        }

    }

    public function fetchProductGallery(SelectConditionGenerator $conditionGenerator)
    {
        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(['sku']);


            $this->joinGallery($select);

            $select
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('product.entity_id', $where);
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield $row;
            }
        }
    }

    public function fetchProductGalleryValues(SelectConditionGenerator $conditionGenerator): iterable
    {
        $storeMap = array_flip(array_intersect_key($this->eavInfo->fetchStoreMap(), $this->storeMap) + ['' => 0]);

        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(['sku']);

            $this->joinGallery($select);

            $select
                ->join(
                    ['gallery_value' => 'catalog_product_entity_media_gallery_value'],
                    'gallery_value.value_id = gallery.value_id',
                    ['store_id', 'label', 'position']
                )
                ->where(
                    function (Where $where) use ($condition, $storeMap) {
                        $condition->apply('product.entity_id', $where);
                        $where->in('store_id', array_keys($storeMap));
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $row['store'] = $this->storeMap[$storeMap[$row['store_id']]] ?? '';
                unset($row['store_id']);
                yield $row;
            }
        }
    }

    public function fetchProductConfigurableAttributes(SelectConditionGenerator $conditionGenerator): iterable
    {
        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(['sku'])
                ->join(
                    ['configurable_attribute' => 'catalog_product_super_attribute'],
                    'configurable_attribute.product_id = product.entity_id',
                    ['position']
                )
                ->join(
                    ['attribute' => 'eav_attribute'],
                    'attribute.attribute_id = configurable_attribute.attribute_id',
                    ['attribute' => 'attribute_code']
                )
                ->join(['label' => 'catalog_product_super_attribute_label'],
                    (new Predicate())
                        ->literal('label.product_super_attribute_id = configurable_attribute.product_super_attribute_id')
                        ->equalTo('label.store_id', 0),
                    ['label' => 'value']
                )
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('product.entity_id', $where);
                        $where->equalTo('product.type_id', 'configurable');
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield $row;
            }
        }
    }

    public function fetchProductConfigurableRelations(SelectConditionGenerator $conditionGenerator): iterable
    {
        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(['sku'])
                ->join(
                    ['link' => 'catalog_product_super_link'],
                    'link.parent_id = product.entity_id',
                    []
                )
                ->join(
                    ['child' => 'catalog_product_entity'],
                    'link.product_id = child.entity_id',
                    ['child_sku' => 'sku']
                )
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('product.entity_id', $where);
                        $where->equalTo('product.type_id', 'configurable');
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield $row;
            }
        }
    }

    private function mapStore(string $storeCode)
    {
        return $this->storeMap[$storeCode] ?? '';
    }

    /**
     * @param Select $select
     *
     */
    protected function joinGallery(Select $select): void
    {
        if ($this->eavInfo->isMagentoTwo()) {
            $select
                ->join(
                    ['gallery_entity' => 'catalog_product_entity_media_gallery_value_to_entity'],
                    'gallery_entity.entity_id = product.entity_id',
                    []
                )
                ->join(
                    ['gallery' => 'catalog_product_entity_media_gallery'],
                    (new Predicate())
                        ->literal('gallery.value_id = gallery_entity.value_id')
                        ->equalTo('gallery.media_type', 'image'),
                    ['image' => 'value']
                );
        } else {
            $select
                ->join(
                    ['gallery' => 'catalog_product_entity_media_gallery'],
                    'gallery.entity_id = product.entity_id',
                    ['image' => 'value']
                );
        }
    }

    public function fetchProductUrls(SelectConditionGenerator $conditionGenerator): iterable
    {
        $storeIds = array_flip(array_intersect_key($this->eavInfo->fetchStoreMap(), $this->storeMap));

        if (!$storeIds) {
            return;
        }

        $rootCategoryIds = [];

        foreach ($this->sql->prepareStatementForSqlObject($this->sql->select('catalog_category_entity')
            ->columns(['entity_id'])
            ->where(['level' => 1]))->execute() as $row) {
            $rootCategoryIds[] = $row['entity_id'];
        }


        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->createUrlRewritesSelect($condition, $storeIds, $rootCategoryIds)
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $row['store'] = $this->storeMap[$storeIds[$row['store_id']]] ?? '';
                unset($row['store_id']);
                yield $row;
            }
        }
    }

    private function createUrlRewritesSelect(SelectCondition $condition, array $storeIds, array $rootCategoryIds): Select
    {
        if ($this->eavInfo->isMagentoTwo()) {
            return $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(
                    ['sku']
                )
                ->join(
                    ['rewrite' => 'url_rewrite'],
                    (new Predicate())
                        ->equalTo('rewrite.entity_type', 'product')
                        ->literal('rewrite.entity_id = product.entity_id'),
                    ['url' => 'request_path', 'store_id']
                )
                ->where(
                    function (Where $where) use ($condition, $storeIds) {
                        $condition->apply('product.entity_id', $where);
                        $where->in('rewrite.store_id', array_keys($storeIds));
                    }
                )
            ;
        }


        return $select = $this->sql->select(['index' => 'catalog_category_product_index'])
            ->columns(['store_id'])
            ->join(
                ['product' => 'catalog_product_entity'],
                'product.entity_id = index.product_id',
                ['sku']
            )
            ->join(
                ['product_rewrite' => 'enterprise_catalog_product_rewrite'],
                (new Predicate())
                    ->literal('product_rewrite.product_id = index.product_id')
                    ->literal('product_rewrite.store_id IN(index.store_id, 0)'),
                []
            )
            ->join(
                ['rewrite' => 'enterprise_url_rewrite'],
                'rewrite.url_rewrite_id = product_rewrite.url_rewrite_id',
                ['url' => 'request_path']
            )
            ->order('product_rewrite.product_id ASC')
            ->order('product_rewrite.store_id ASC')
            ->where(function (Where $where) use ($condition, $storeIds, $rootCategoryIds) {
                $where->notIn('index.visibility', [1]);
                $condition->apply('index.product_id', $where);
                $where->in('index.store_id', array_keys($storeIds));
                $where->in('index.category_id', $rootCategoryIds);
            })
        ;
    }

    /**
     * @param SelectConditionGenerator $conditionGenerator
     *
     * @return \Generator
     */
    protected function generateProductAttributeData(SelectConditionGenerator $conditionGenerator): \Generator
    {
        $varcharType = ['type' => 'varchar'];
        $attributes = [
            'url_path' => $varcharType
        ];
        foreach (['image', 'small_image', 'thumbnail'] as $attribute) {
            $attributes[$attribute] = $varcharType;
            $attributes[$attribute . '_label'] = $varcharType;
        }

        $attributes += $this->eavInfo->fetchProductAttributes();
        $attributesToIgnore = ["associated_cache","associated_cache_10","associated_cache_11","associated_cache_12","associated_cache_13","associated_cache_14","associated_cache_15","associated_cache_16","associated_cache_2","associated_cache_3","associated_cache_4","associated_cache_5","associated_cache_6","associated_cache_7","associated_cache_8","associated_cache_9","sizes_cache","size_cache_10","size_cache_11","size_cache_12","size_cache_13","size_cache_14","size_cache_15","size_cache_16","size_cache_2","size_cache_3","size_cache_4","size_cache_5","size_cache_6","size_cache_7","size_cache_8","size_cache_9"];

        $attributes = array_diff_key(
            $attributes,
            array_combine($attributesToIgnore, $attributesToIgnore)
        );

        $attributeCodes = array_keys($attributes);
        foreach ($this->ignoredAttributes as $attribute) {
            unset($attributes[$attribute]);
        }

        $attributeIds = $this->eavInfo->fetchAttributeIds('catalog_product', $attributeCodes);
        $storeMap = array_flip(array_intersect_key($this->eavInfo->fetchStoreMap(), $this->storeMap));

        $select = $this->sql->select(['option' => 'eav_attribute_option'])
            ->columns(['id' => 'option_id', 'attribute_id'])
            ->join(['name' => 'eav_attribute_option_value'], 'name.option_id = option.option_id', ['value'])
            ->where(
                function (Where $where) use ($attributeIds) {
                    $where->in('option.attribute_id', $attributeIds);
                    $where->equalTo('store_id', 0);
                }
            )
        ;

        $optionMap = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $optionMap[$row['attribute_id']][$row['id']] = $row['value'];
        }

        $attributeByTable = [];
        $isMultiple = [];
        foreach ($attributes as $code => $info) {
            $tableName = 'catalog_product_entity_' . $info['type'];
            $attributeByTable[$tableName][] = $attributeIds[$code];
        }

        $isMultiple['catalog_product_entity_varchar'] = new Expression('(attribute.value REGEXP ?)', '^[0-9]+,');
        $isMultiple['catalog_product_entity_text'] = new Expression('(attribute.value REGEXP ?)', '^[0-9]+,');

        $attributeCodeToId = array_flip($attributeIds);
        foreach ($conditionGenerator->conditions() as $condition) {
            $selects = [
                $this->sql->select(['entity' => 'catalog_product_entity'])
                    ->columns(
                        [
                            'attribute_id' => new Expression('?', 'entity_id'),
                            'store_id' => new Literal('0'),
                            'value' => new Expression('LPAD(entity_id, ?, ?)', [10, '0']),
                            'is_multiple' => new Literal('0'),
                            'sku',
                        ]
                    )
                    ->where(
                        function (Where $where) use ($condition) {
                            $condition->apply('entity_id', $where);
                        }
                    )
            ];

            foreach ($attributeByTable as $table => $attributes) {
                $selects[] = $this->sql->select(['attribute' => $table])
                    ->columns(
                        ['attribute_id', 'store_id', 'value', 'is_multiple' => $isMultiple[$table] ?? new Literal('0')]
                    )
                    ->join(['product' => 'catalog_product_entity'], 'product.entity_id = attribute.entity_id', ['sku'])
                    ->where(
                        function (Where $where) use ($condition, $attributes, $storeMap) {
                            $condition->apply('attribute.entity_id', $where);
                            $where->in('attribute.attribute_id', $attributes);
                            $where->in('attribute.store_id', array_merge([0], array_keys($storeMap)));
                        }
                    )
                ;
            }

            foreach ($this->sql
                         ->prepareStatementForSqlObject(
                             new Combine($selects, Combine::COMBINE_UNION, Select::QUANTIFIER_ALL)
                         )
                         ->execute() as $row) {
                if ($row['store_id'] === '0' && $row['value'] == null) {
                    continue;
                }

                yield [
                    'sku' => $row['sku'],
                    'attribute' => $attributeCodeToId[$row['attribute_id']] ?? $row['attribute_id'],
                    'value' => $this->resolveOptionId($optionMap, $row),
                    'store' => $this->mapStore($storeMap[$row['store_id']] ?? '')
                ];
            }
        }
    }

    private function applyMappers(string $code, iterable $data): iterable
    {
        if (isset($this->rowMappers[$code])) {
            return $this->rowMappers[$code]->apply($data);
        }

        return $data;
    }

    public function getSuperLinkTypeId(): int
    {
        $select = $this->sql->select()
            ->columns(['id' => 'link_type_id'])
            ->from('catalog_product_link_type')
            ->where(
                function (Where $where) {
                    $where->equalTo('code', 'super');
                }
            )
        ;

        return (int)$this->sql->prepareStatementForSqlObject($select)->execute()->current()['id'];
    }

    public function fetchGroupedProductRelations(SelectConditionGenerator $conditionGenerator): iterable
    {
        $linkTypeId = $this->getSuperLinkTypeId();

        foreach ($conditionGenerator->conditions() as $condition) {
            $select = $this->sql->select(['product' => 'catalog_product_entity'])
                ->columns(['product_sku' => 'sku'])
                ->join(
                    ['link' => 'catalog_product_link'],
                    'link.product_id = product.entity_id',
                    []
                )
                ->join(
                    ['linked_product' => 'catalog_product_entity'],
                    'link.linked_product_id = linked_product.entity_id',
                    ['linked_product_sku' => 'sku']
                )
                ->where(
                    function (Where $where) use ($condition, $linkTypeId) {
                        $condition->apply('product.entity_id', $where);
                        $where->equalTo('product.type_id', 'grouped');
                        $where->equalTo('link.link_type_id', (int)$linkTypeId);
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                yield $row;
            }
        }
    }
}
