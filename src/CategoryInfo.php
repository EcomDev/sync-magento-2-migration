<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Laminas\Db\Metadata\MetadataInterface;
use Laminas\Db\Sql\Combine;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\DropTable;
use Laminas\Db\Sql\Ddl\Index\Index;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class CategoryInfo
{
    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;
    /**
     * @var Sql
     */
    private $sql;

    /** @var string */
    private $mapTable;

    /** @var string[] */
    private $activeFilterStores;
    /**
     * @var array
     */
    private $storeMap;
    /**
     * @var MetadataInterface
     */
    private $metadata;

    public function __construct(MagentoEavInfo $eavInfo, Sql $sql, MetadataInterface $metadata, array $activeFilterStores, array $storeMap)
    {
        $this->eavInfo = $eavInfo;
        $this->sql = $sql;
        $this->activeFilterStores = $activeFilterStores;
        $this->storeMap = $storeMap;
        $this->metadata = $metadata;
    }

    public function fetchCategoryTree()
    {
        $categoryMapTable = $this->createCategoryMapTableIfNotExists();
        $categoryAttributeIds = $this->eavInfo->fetchAttributeIds('catalog_category', ['name', 'is_active']);

        $select = $this->sql->select(['category' => $categoryMapTable])
            ->columns(['category_id', 'path_ids'])
            ->join(
                ['is_active' => 'catalog_category_entity_int'],
                (new Predicate())
                    ->equalTo('is_active.attribute_id', $categoryAttributeIds['is_active'])
                    ->equalTo('is_active.store_id', 0)
                    ->literal('is_active.entity_id = category.category_id')
                    ->equalTo('is_active.value', 1),
                []
            )
            ->join(
                ['name' => 'catalog_category_entity_varchar'],
                (new Predicate())
                    ->equalTo('name.attribute_id', $categoryAttributeIds['name'])
                    ->equalTo('name.store_id', 0)
                    ->literal('name.entity_id = category.category_id'),
                ['name' => 'value']
            )
            ->order('category.path ASC')
        ;

        $categories = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $categories[$row['category_id']] = $row;
        }

        $result = [];
        foreach ($categories as $row) {
            $names = [];
            foreach (explode(',', $row['path_ids']) as $parentId) {
                if (!isset($categories[$parentId])) {
                    continue;
                }
                $names[] = $categories[$parentId]['name'];
            }

            $result[implode(' / ', $names)] = $row['category_id'];
        }

        return $result;
    }

    private function createCategoryMapTableIfNotExists(): string
    {
        if (!$this->mapTable) {
            $this->mapTable = uniqid('category_map');
            $table = new CreateTable($this->mapTable);
            $table->addColumn(new Integer('category_id'))
                ->addColumn(new Varchar('path_ids', 255))
                ->addColumn(new Varchar('path', 255))
                ->addColumn(new Integer('level'))
                ->addConstraint(new PrimaryKey('category_id'))
                ->addConstraint(new Index('path_ids'))
                ->addConstraint(new Index('path'))
                ->addConstraint(new Index('level'))
            ;

            $this->sql->getAdapter()->getDriver()->getConnection()->execute($this->sql->buildSqlString($table));

            $this->sql->prepareStatementForSqlObject($this->sql->insert($this->mapTable)
                ->values($this->sql->select('catalog_category_entity')
                    ->columns([
                        'category_id' => 'entity_id',
                        'path_ids' => new Expression('REPLACE(path, ?, ?)', ['/', ',']),
                        'path',
                        'level'
                    ])
                    ->where(function (Where $where) {
                        $where->greaterThan('level', 1);
                    })))
                ->execute();
        }


        return $this->mapTable;
    }

    public function fetchMainCategoryRows(): iterable
    {
        if (!$this->activeFilterStores) {
            return;
        }

        foreach ($this->sql->prepareStatementForSqlObject(
            $this->createExportableCategorySelect()
        )->execute() as $row) {
            yield [
                'name' => $row['name'],
                'id' => $this->exportId($row['category_id']),
                'parent_path' => $this->mapParent(explode(',', $row['path_ids']))
            ];
        }
    }

    public function __destruct()
    {
        if ($this->mapTable) {
            $this->sql->getAdapter()->getDriver()->getConnection()->execute(
                $this->sql->buildSqlString(new DropTable($this->mapTable))
            );
        }
    }

    private function mapParent(array $parentIds)
    {
        return implode('/', array_map(
            function ($value) {
                return $this->exportId($value);
            },
            array_slice($parentIds, 2, -1)
        ));
    }

    private function exportId(string $id) {
        return 'category_' . $id;
    }

    private function createExportableCategorySelect(): Select
    {
        $storeIds = array_intersect_key(
            $this->eavInfo->fetchStoreMap(),
            array_combine($this->activeFilterStores, $this->activeFilterStores)
        );

        $categoryMapTable = $this->createCategoryMapTableIfNotExists();
        $categoryAttributeIds = $this->eavInfo->fetchAttributeIds('catalog_category', ['name', 'is_active']);

        $select = $this->sql->select(['parent_category' => $categoryMapTable])
            ->join(
                ['child_category' => $categoryMapTable],
                (new Predicate())
                    ->literal('FIND_IN_SET(parent_category.category_id, child_category.path_ids)'),
                ['category_id', 'path_ids']
            )
            ->columns([])
            ->join(
                ['is_active_default' => 'catalog_category_entity_int'],
                (new Predicate())
                    ->equalTo('is_active_default.attribute_id', $categoryAttributeIds['is_active'])
                    ->equalTo('is_active_default.store_id', 0)
                    ->literal('is_active_default.entity_id = parent_category.category_id'),
                []
            )
            ->join(
                ['name' => 'catalog_category_entity_varchar'],
                (new Predicate())
                    ->equalTo('name.attribute_id', $categoryAttributeIds['name'])
                    ->equalTo('name.store_id', 0)
                    ->literal('name.entity_id = child_category.category_id'),
                ['name' => 'value']
            )
            ->join(
                ['is_active_store' => 'catalog_category_entity_int'],
                (new Predicate())
                    ->equalTo('is_active_store.attribute_id', $categoryAttributeIds['is_active'])
                    ->in('is_active_store.store_id', $storeIds)
                    ->literal('is_active_store.entity_id = parent_category.category_id'),
                [],
                Select::JOIN_LEFT
            )
            ->where(
                function (Where $where) {
                    $where->equalTo('parent_category.level', 2);
                    $where->expression('IFNULL(is_active_store.value, is_active_default.value) = ?', [1]);
                }
            )
            ->group('child_category.category_id')
            ->order('child_category.path ASC')
        ;

        return $select;
    }

    public function fetchCategoryData(array $attributeCodes): iterable
    {
        $categoryIds = [];

        foreach ($this->sql->prepareStatementForSqlObject($this->createExportableCategorySelect())->execute() as $row) {
            $categoryIds[] = $row['category_id'];
        }

        $storeIds = array_intersect_key($this->eavInfo->fetchStoreMap(), $this->storeMap);
        $storeIds[''] = 0;
        $storeMap = array_flip($storeIds);
        $attributeIds = $this->eavInfo->fetchAttributeIds('catalog_category', $attributeCodes);
        $attributeCodes = array_flip($attributeIds);

        $tables = array_intersect([
            'catalog_category_entity_varchar',
            'catalog_category_entity_url_key',
            'catalog_category_entity_int',
            'catalog_category_entity_text'
        ], $this->metadata->getTableNames());

        $selects = [];

        foreach ($tables as $table) {
            $selects[] = $this->sql->select($table)
                ->columns(['entity_id', 'store_id', 'attribute_id', 'value'])
                ->where(function (Where $where) use ($categoryIds, $attributeIds, $storeIds) {
                    $where->in('entity_id', $categoryIds);
                    $where->in('attribute_id', $attributeIds);
                    $where->in('store_id', $storeIds);
                });
        }

        foreach ($this->sql->prepareStatementForSqlObject(
            (new Combine())->union($selects, Select::QUANTIFIER_ALL)
        )->execute() as $row) {
            yield [
                'attribute' => $attributeCodes[$row['attribute_id']],
                'id' => $this->exportId($row['entity_id']),
                'value' => $row['value'],
                'store' => $this->storeMap[$storeMap[$row['store_id']]] ?? ''
            ];
        }

    }
}
