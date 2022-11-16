<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Metadata\MetadataInterface;
use Laminas\Db\Metadata\Source\Factory;
use Laminas\Db\Sql\Ddl\Column\Integer;
use Laminas\Db\Sql\Ddl\Column\Varchar;
use Laminas\Db\Sql\Ddl\Constraint\PrimaryKey;
use Laminas\Db\Sql\Ddl\CreateTable;
use Laminas\Db\Sql\Ddl\Index\Index;
use Laminas\Db\Sql\Predicate\Predicate;
use Laminas\Db\Sql\Select;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class CategoryImport
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
     * @var MetadataInterface
     */
    private $metadata;

    /** @var string */
    private $mappingTable;

    private const ROOT_PATH = [1, 2];

    public function __construct(MagentoEavInfo $info, Sql $sql, MetadataInterface $metadata)
    {
        $this->info = $info;
        $this->sql = $sql;
        $this->metadata = $metadata;
    }

    public static function createFromAdapter(Adapter $adapter)
    {
        return new self(
            MagentoEavInfo::createFromAdapter($adapter),
            new Sql($adapter),
            Factory::createSourceFromAdapter($adapter)
        );
    }

    public function importCategories(iterable $categories): void
    {
        $attributeSetId = current($this->info->fetchDefaultEntityAttributeSet('catalog_category'));
        $attributeIds = $this->info->fetchAttributeIds('catalog_category', ['name', 'is_active']);

        $dataToImport = [];
        $positions = [];
        $externalCategoryIds = [];
        foreach ($categories as $category) {
            $positions[$category['parent_path']] = ($positions[$category['parent_path']] ?? 0) + 10;
            $externalCategoryIds[] = $category['id'];
            $dataToImport[] = $category + ['position' => $positions[$category['parent_path']]];
        }

        $externalCategoryMap = $this->resolveExternalCategoryIds($externalCategoryIds);

        $entityInsert = InsertOnDuplicate::create(
            'catalog_category_entity',
            ['entity_id', 'attribute_set_id', 'parent_id', 'path', 'level', 'position', 'children_count']
        )->onDuplicate(['level', 'path', 'position', 'parent_id'])
        ;

        $nameInsert = $this->createAttributeInsert('varchar')
            ->onDuplicate(['value'])
        ;

        // Do not re-enable already disabled categories
        $isActiveInsert = $this->createAttributeInsert('int')
            ->onDuplicate(['store_id'])
        ;

        foreach ($dataToImport as $row) {
            $path = self::ROOT_PATH;
            $parents = array_filter(explode('/', $row['parent_path']));
            foreach ($parents as $parent) {
                $path[] = $externalCategoryMap[$parent];
            }
            $path[] = $externalCategoryMap[$row['id']];

            $entityInsert->withRow(
                $externalCategoryMap[$row['id']],
                $attributeSetId,
                $path[count($path) - 2],
                implode('/', $path),
                count($path) - 1,
                $row['position'],
                0
            );

            $nameInsert->withRow(
                $externalCategoryMap[$row['id']],
                $attributeIds['name'],
                0,
                $row['name']
            );

            $isActiveInsert->withRow($externalCategoryMap[$row['id']], $attributeIds['is_active'], 0,  1);
        }

        $entityInsert->executeIfNotEmpty($this->sql);
        $nameInsert->executeIfNotEmpty($this->sql);
        $isActiveInsert->executeIfNotEmpty($this->sql);
    }

    public function importCategoryAttributes(iterable $categoryAttributes)
    {
        $types = ['int', 'varchar', 'decimal', 'datetime', 'text'];
        $attributeInfo = $this->fetchImportableCategoryAttributes($types);

        $categoryMap = $this->resolveExternalCategoryIds();
        $storeMap = $this->info->fetchStoreMap();

        /** @var InsertOnDuplicate[] $attributeInserts */
        $attributeInserts = [];
        foreach ($types as $type) {
            $attributeInserts[$type] = $this->createAttributeInsert($type)
                ->onDuplicate(['value']);
        }

        foreach ($categoryAttributes as $row) {
            if (($row['store'] && !isset($storeMap[$row['store']]))
                || !isset($attributeInfo[$row['attribute']])
                || !isset($categoryMap[$row['id']])) {
                continue;
            }

            $attribute = $attributeInfo[$row['attribute']];

            $insert = $attributeInserts[$attribute['type']];

            $insert->withRow(
                $categoryMap[$row['id']],
                $attribute['id'],
                $row['store'] ? $storeMap[$row['store']] : 0,
                $row['value']
            );

            $attributeInserts[$attribute['type']] = $insert->flushIfLimitReached($this->sql);
        }

        foreach ($attributeInserts as $insert) {
            $insert->executeIfNotEmpty($this->sql);
        }

        $this->generateUrlRewrites();
    }

    public function createCategoryMapTableIfNotExists(): string
    {
        if (!$this->mappingTable) {
            $this->mappingTable = 'catalog_category_migration_m1_category_map';

            if (!in_array($this->mappingTable, $this->metadata->getTableNames())) {
                $table = new CreateTable($this->mappingTable);
                $table->addColumn(new Integer('entity_id'))
                    ->addColumn(new Varchar('foreign_id', 255))
                    ->addConstraint(new PrimaryKey('entity_id'))
                    ->addConstraint(new Index('foreign_id'))
                ;

                $this->sql->getAdapter()->getDriver()->createStatement(
                    $this->sql->buildSqlString($table)
                )->execute()
                ;
            }

        }

        return $this->mappingTable;
    }

    private function allocateEntityIds(int $number): array
    {
        $insert = $this->sql->insert('catalog_category_entity')->values(
            ['parent_id' => '2', 'path' => '', 'position' => 0, 'children_count' => 0]
        );
        $this->sql->getAdapter()->getDriver()->getConnection()->beginTransaction();

        $ids = [];
        while (count($ids) < $number) {
            $ids[] = $this->sql->prepareStatementForSqlObject($insert)->execute()->getGeneratedValue();
        }

        $this->sql->getAdapter()->getDriver()->getConnection()->rollback();

        return $ids;
    }

    private function resolveExternalCategoryIds(array $externalCategoryIds = []): array
    {
        $tableName = $this->createCategoryMapTableIfNotExists();

        $select = $this->sql->select($tableName)
            ->columns(['entity_id', 'foreign_id'])
        ;


        $existingMap = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $existingMap[$row['foreign_id']] = (int)$row['entity_id'];
        }

        $idsToAllocate = [];
        foreach ($externalCategoryIds as $id) {
            if (!isset($existingMap[$id])) {
                $idsToAllocate[] = $id;
            }
        }

        if ($idsToAllocate) {
            $ids = $this->allocateEntityIds(count($idsToAllocate));

            $insert = InsertOnDuplicate::create($tableName, ['entity_id', 'foreign_id'])
                ->onDuplicate(['foreign_id'])
            ;

            foreach ($idsToAllocate as $foreignId) {
                $entityId = array_shift($ids);
                $insert->withRow($entityId, $foreignId);
                $existingMap[$foreignId] = $entityId;
            }

            $insert->executeIfNotEmpty($this->sql);
        }

        return $existingMap;
    }

    /**
     * @param string $type
     *
     * @return InsertOnDuplicate
     */
    private function createAttributeInsert(string $type): InsertOnDuplicate
    {
        return InsertOnDuplicate::create(
            'catalog_category_entity_' . $type,
            ['entity_id', 'attribute_id', 'store_id', 'value']
        );
    }

    /**
     *
     * @return array
     */
    private function fetchImportableCategoryAttributes(array $types): array
    {
        $attributeInfoSelect = $this->sql->select('eav_attribute')
            ->columns(['id' => 'attribute_id', 'code' => 'attribute_code', 'type' => 'backend_type'])
            ->where(
                function (Where $where) use ($types) {
                    $where->equalTo('entity_type_id', $this->info->fetchEntityTypes()['catalog_category']);
                    $where->in('backend_type', $types);
                }
            )
        ;

        $attributeInfo = [];
        foreach ($this->sql->prepareStatementForSqlObject($attributeInfoSelect)->execute() as $attribute) {
            $attributeInfo[$attribute['code']] = $attribute;
        }

        return $attributeInfo;
    }

    private function generateUrlRewrites(): void
    {
        $stores = $this->info->fetchStoreMap();
        $attributeIds = $this->info->fetchAttributeIds('catalog_category', ['name', 'url_key']);
        $insert = InsertOnDuplicate::create(
            'url_rewrite',
            ['entity_type', 'entity_id', 'request_path', 'target_path', 'store_id', 'is_autogenerated']
        )
            ->onDuplicate(['entity_type', 'entity_id', 'target_path', 'is_autogenerated'])
        ;

        $urlSelect = $this->sql->select()
            ->from(['category' => 'catalog_category_entity'])
            ->columns(['entity_id', 'path'])
            ->join(
                ['name' => 'catalog_category_entity_varchar'],
                (new Predicate())
                    ->literal('name.entity_id = category.entity_id')
                    ->equalTo('name.attribute_id', $attributeIds['name'])
                    ->equalTo('name.store_id', 0),
                ['name' => 'value']
            )
            ->join(
                ['url_key' => 'catalog_category_entity_varchar'],
                (new Predicate())
                    ->literal('url_key.entity_id = category.entity_id')
                    ->equalTo('url_key.attribute_id', $attributeIds['url_key'])
                    ->equalTo('url_key.store_id', 0),
                ['url_key' => 'value'],
                Select::JOIN_LEFT
            )
            ->where(function (Where $where) {
                $where->greaterThan('category.level', 1);
            })
            ->order('category.path asc');

        $requestPathByCategory = [];

        foreach ($this->sql->prepareStatementForSqlObject($urlSelect)->execute() as $row) {
            $parentPath = implode('/', array_slice(explode('/', $row['path']), 0, -1));
            $prefix = (isset($requestPathByCategory[$parentPath]['request_path'])
                ? $requestPathByCategory[$parentPath]['request_path'] . '/'
                : '');

            $requestPathByCategory[$row['path']]['request_path'] = $prefix . ($row['url_key'] ?: $this->generateUrlKey($row['name']));
            $requestPathByCategory[$row['path']]['target_path'] = sprintf('catalog/category/view/id/%s', $row['entity_id']);
            $requestPathByCategory[$row['path']]['entity_type'] = 'category';
            $requestPathByCategory[$row['path']]['entity_id'] = $row['entity_id'];
        }

        foreach ($stores as $storeId) {
            foreach ($requestPathByCategory as $category) {
                $insert->values(
                    $category + ['store_id' => $storeId, 'is_autogenerated' => 1],
                    InsertOnDuplicate::VALUES_MERGE
                );
                $insert = $insert->flushIfLimitReached($this->sql);
            }
        }

        $insert->executeIfNotEmpty($this->sql);
    }

    private function generateUrlKey($name)
    {
        $urlKey = preg_replace('/\s+/', '-', strtolower($name));
        return $urlKey;
    }
}
