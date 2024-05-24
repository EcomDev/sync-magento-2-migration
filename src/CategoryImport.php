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
    /** @var string */
    private $mappingTable;

    private const ROOT_PATH = [1, 2];

    public function __construct(
        private readonly MagentoEavInfo $info,
        private readonly Sql $sql,
        private readonly Sql $readSql,
        private readonly MetadataInterface $metadata
    ) {
    }

    public static function createFromAdapter(Adapter $adapter, Adapter $readAdapter)
    {
        return new self(
            MagentoEavInfo::createFromAdapter($adapter),
            new Sql($adapter),
            new Sql($readAdapter),
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

            $isActiveInsert->withRow(
                $externalCategoryMap[$row['id']],
                $attributeIds['is_active'],
                0,
                1
            );

            $entityInsert->flushIfLimitReached($this->sql, 100);
            $nameInsert->flushIfLimitReached($this->sql, 100);
            $isActiveInsert->flushIfLimitReached($this->sql, 100);
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

            $insert->flushIfLimitReached($this->sql);
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
                )->execute();
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

        $insert = InsertOnDuplicate::create(
            'url_rewrite',
            ['entity_type', 'entity_id', 'request_path', 'target_path', 'store_id', 'is_autogenerated']
        )
            ->onDuplicate(['entity_type', 'entity_id', 'target_path', 'is_autogenerated'])
        ;

        $parentByLevel = [];
        foreach($this->loadCategories($stores) as $row) {
            $parentByLevel[$row['level']] = [];

            foreach ($stores as $storeId) {
                $parentPath = $parentByLevel[$row['level'] - 1][$storeId] ?? false;
                $urlKey = $row['url_key'][$storeId] ?? $row['url_key'][0] ?? $this->generateUrlKey(
                    $row['name'][$storeId] ?? $row['name'][0]
                );

                $urlPath =  ($parentPath ? $parentPath . '/' : '') . $urlKey;
                $parentByLevel[$row['level']][$storeId] = $urlPath;

                $insert->withRow(
                    'category',
                    $row['entity_id'],
                    $urlPath,
                    sprintf('catalog/category/view/id/%d', $row['entity_id']),
                    $storeId,
                    1
                );

                $insert->flushIfLimitReached($this->sql, 1000);
            }
        }

        $insert->executeIfNotEmpty($this->sql);
    }

    /**
     * @param array $stores
     * @return Select
     */
    public function loadCategories(array $stores): iterable
    {
        $attributeIds = $this->info->fetchAttributeIds('catalog_category', ['name', 'url_key']);
        $attributeCodes = array_flip($attributeIds);

        $urlSelect = $this->readSql->select()
            ->from(['category' => 'catalog_category_entity'])
            ->columns(['entity_id', 'path', 'level'])
            ->join(
                ['attribute' => 'catalog_category_entity_varchar'],
                (new Predicate())
                    ->literal('attribute.entity_id = category.entity_id')
                    ->in('attribute.attribute_id', $attributeIds)
                    ->in('attribute.store_id', [0, ...$stores]),
                ['attribute_id', 'store_id', 'value']
            )
            ->where(function (Where $where) {
                $where->greaterThan('category.level', 1);
            })
            ->order('category.path asc');

        $currentRow = [];

        foreach ($this->readSql->prepareStatementForSqlObject($urlSelect)->execute() as $row) {
            if ($currentRow && $currentRow['entity_id'] != $row['entity_id']) {
                yield $currentRow;
                $currentRow = [];
            }

            if (!$currentRow) {
                $currentRow = [
                    'entity_id' => $row['entity_id'],
                    'path' => $row['path'],
                    'level' => $row['level']
                ];
            }

            $currentRow[$attributeCodes[$row['attribute_id']]][$row['store_id']] = $row['value'];
        }

        if ($currentRow) {
            yield $currentRow;
        }
    }

    private function generateUrlKey($name)
    {
        $urlKey = preg_replace('/\s+/', '-', strtolower($name));
        return $urlKey;
    }
}
