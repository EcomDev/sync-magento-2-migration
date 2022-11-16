<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use Generator;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Adapter\Driver\ConnectionInterface;
use Laminas\Db\Sql\Insert;
use Laminas\Db\Sql\Sql;
use Laminas\Db\Sql\Where;

class EavMetadataImport
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
     * @var ConnectionInterface
     */
    private $connection;

    public function __construct(Sql $sql, MagentoEavInfo $eavInfo, ConnectionInterface $connection)
    {
        $this->sql = $sql;
        $this->eavInfo = $eavInfo;
        $this->connection = $connection;
    }

    public static function createFromAdapter(Adapter $adapter): self
    {
        $sql = new Sql($adapter);

        return new self($sql, MagentoEavInfo::createFromAdapter($adapter), $adapter->getDriver()->getConnection());
    }

    private function transactional(callable $action)
    {
        $this->connection->beginTransaction();
        try {
            $action();
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollback();
            throw $exception;
        }
    }

    public function importAttributes(iterable $attributes)
    {
        $attributeIds = $this->eavInfo->fetchAllProductAttributeIds();
        $entityTypes = $this->eavInfo->fetchEntityTypes();

        $this->transactional(
            function () use ($attributes, $attributeIds, $entityTypes) {
                $additionalDataInsert = InsertOnDuplicate::create(
                    'catalog_eav_attribute',
                    [
                        'attribute_id',
                        'is_global',
                        'is_searchable',
                        'is_filterable',
                        'is_comparable',
                        'is_visible_on_front',
                        'is_html_allowed_on_front',
                        'is_used_for_price_rules',
                        'is_filterable_in_search',
                        'used_in_product_listing',
                        'used_for_sort_by',
                        'apply_to',
                        'is_wysiwyg_enabled',
                        'is_visible_in_advanced_search',
                        'is_used_for_promo_rules',
                        'position',
                    ]
                )
                    ->onDuplicate(
                        [
                            'is_global',
                            'is_searchable',
                            'is_filterable',
                            'is_comparable',
                            'is_visible_on_front',
                            'is_html_allowed_on_front',
                            'is_used_for_price_rules',
                            'is_filterable_in_search',
                            'used_in_product_listing',
                            'used_for_sort_by',
                            'apply_to',
                            'is_wysiwyg_enabled',
                            'is_visible_in_advanced_search',
                            'is_used_for_promo_rules',
                            'position',
                        ]
                    )
                ;

                foreach ($attributes as $info) {
                    if (!isset($attributeIds[$info['code']])) {
                        $attributeIds[$info['code']] = $this->createAttribute($info, $entityTypes['catalog_product']);
                    }

                    $this->updateAttribute($attributeIds[$info['code']], $info);

                    $scopeMap = [
                        'global' => '1',
                        'store' => '0',
                        'website' => '2',
                    ];

                    $additionalDataInsert->withRow(
                        $attributeIds[$info['code']],
                        $scopeMap[$info['scope']],
                        $info['searchable'],
                        $info['layered'],
                        $info['comparable'],
                        $info['product_page'],
                        $info['html'],
                        $info['promotion'],
                        $info['layered_search'],
                        $info['product_list'],
                        $info['sortable'],
                        $info['apply_to'],
                        $info['html'],
                        $info['advanced_search'],
                        $info['promotion'],
                        $info['position']
                    );
                }

                $additionalDataInsert->executeIfNotEmpty($this->sql);
            }
        );
    }

    public function importAttributeSets(iterable $attributeSets)
    {
        $this->transactional(
            function () use ($attributeSets) {
                $defaultSets = [];
                $otherSets =[];

                foreach ($attributeSets as $set) {
                    if ($set['set'] === 'Default') {
                        $defaultSets[] = $set;
                        continue;
                    }

                    $otherSets[] = $set;
                }

                if ($defaultSets) {
                    $this->importAttributeSetsIntoDatabase(
                        $defaultSets
                    );
                }

                if ($otherSets) {
                    $this->importAttributeSetsIntoDatabase(
                        $otherSets
                    );
                }
            }
        );
    }

    private function createAttributeSet($setInfo, $fromSet): int
    {
        $attributeSetId = $this->sql
            ->prepareStatementForSqlObject(
                $this->sql->insert('eav_attribute_set')
                    ->values($setInfo)
            )->execute()
            ->getGeneratedValue()
        ;

        $defaultGroups = $select = $this->sql->select('eav_attribute_group')
            ->columns(
                [
                    'attribute_group_id',
                    'attribute_group_name',
                    'sort_order',
                    'attribute_group_code',
                    'default_id',
                    'tab_group_code',
                ]
            )
            ->where(
                function (Where $where) use ($fromSet) {
                    $where->equalTo('attribute_set_id', $fromSet);
                }
            )
        ;

        $defaultAttributes = $select = $this->sql->select('eav_entity_attribute')
            ->columns(
                [
                    'attribute_group_id',
                    'attribute_id',
                    'entity_type_id',
                    'sort_order',
                ]
            )
            ->where(
                function (Where $where) use ($fromSet) {
                    $where->equalTo('attribute_set_id', $fromSet);
                }
            )
        ;

        $groupMap = [];

        foreach (iterator_to_array($this->sql->prepareStatementForSqlObject($defaultGroups)->execute()) as $info) {
            $groupMap[$info['attribute_group_id']] = $this->createAttributeGroup($attributeSetId, $info);
        }

        $attributeStatement = InsertOnDuplicate::create(
            'eav_entity_attribute',
            [
                'entity_type_id',
                'attribute_set_id',
                'attribute_group_id',
                'attribute_id',
                'sort_order',
            ]
        )->onDuplicate(['attribute_group_id', 'sort_order']);

        foreach ($this->sql->prepareStatementForSqlObject($defaultAttributes)->execute() as $row) {
            $attributeStatement->withAssocRow(
                [
                    'attribute_group_id' => $groupMap[$row['attribute_group_id']],
                    'attribute_set_id' => $attributeSetId,
                ] + $row
            );
        }

        $attributeStatement->executeIfNotEmpty($this->sql);

        return (int)$attributeSetId;
    }

    private function fetchAttributeIds(array $existingAttributes): array
    {
        $select = $this->sql->select('eav_attribute')
            ->columns(['attribute_code', 'attribute_id'])
            ->where(
                function (Where $where) use ($existingAttributes) {
                    $where->in('attribute_code', array_keys($existingAttributes));
                }
            )
        ;

        $result = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['attribute_code']] = (int)$row['attribute_id'];
        }

        return $result;
    }

    private function createAttribute($attributeInfo, int $entityTypeId): int
    {
        $insert = $this->sql->insert('eav_attribute')->values(
            [
                'attribute_code' => $attributeInfo['code'],
                'backend_type' => $attributeInfo['type'],
                'frontend_input' => $attributeInfo['input'],
                'frontend_label' => $attributeInfo['name'],
                'frontend_class' => $attributeInfo['validation'],
                'is_required' => $attributeInfo['required'],
                'is_user_defined' => 1,
                'default_value' => $attributeInfo['default'],
                'is_unique' => $attributeInfo['unique'],
                'entity_type_id' => $entityTypeId,
            ],
            Insert::VALUES_SET
        )
        ;

        return (int)$this->sql->prepareStatementForSqlObject($insert)->execute()->getGeneratedValue();
    }

    private function updateAttribute(int $attributeId, $attributeInfo)
    {
        $update = $this->sql->update('eav_attribute')->set(
            [
                'attribute_code' => $attributeInfo['code'],
                'backend_type' => $attributeInfo['type'],
                'frontend_input' => $attributeInfo['input'],
                'frontend_label' => $attributeInfo['name'],
                'frontend_class' => $attributeInfo['validation'],
                'is_required' => $attributeInfo['required'],
                'default_value' => $attributeInfo['default'],
                'is_unique' => $attributeInfo['unique'],
            ],
            Insert::VALUES_SET
        )
            ->where(
                function (Where $where) use ($attributeId) {
                    $where->equalTo('attribute_id', $attributeId);
                }
            )
        ;

        $this->sql->prepareStatementForSqlObject($update)->execute();
    }

    private function fetchAttributeSets()
    {
        $select = $this->sql->select()
            ->from(['attribute_relation' => 'eav_entity_attribute'])
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute_relation.entity_type_id',
                []
            )
            ->columns(['sort_order'])
            ->join(
                ['attribute_set' => 'eav_attribute_set'],
                'attribute_set.attribute_set_id = attribute_relation.attribute_set_id',
                ['attribute_set_name', 'attribute_set_id']
            )
            ->join(
                ['attribute' => 'eav_attribute'],
                'attribute_relation.attribute_id = attribute.attribute_id',
                ['attribute_code']
            )
            ->join(
                ['attribute_group' => 'eav_attribute_group'],
                'attribute_group.attribute_group_id = attribute_relation.attribute_group_id',
                ['attribute_group_name', 'attribute_group_id', 'group_order' => 'sort_order']
            )
            ->order('attribute_set.attribute_set_id ASC')
            ->order('attribute_group.sort_order ASC')
            ->order('attribute_relation.sort_order ASC')
            ->where(
                function (Where $where) {
                    $where->equalTo('entity_type.entity_type_code', 'catalog_product');
                }
            )
        ;


        $result = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $set = $result[$row['attribute_set_name']] ?? [
                'groups' => [],
                'id' => $row['attribute_set_id'],
            ];

            $set['group_sort'] = 5;

            $set['groups'][$row['attribute_group_name']] = $set['groups'][$row['attribute_group_name']] ?? [
                'attributes' => [],
                'id' => $row['attribute_group_id'],
            ];

            $set['groups'][$row['attribute_group_name']]['attributes'][$row['attribute_code']] = $row['sort_order'];

            $result[$row['attribute_set_name']] = $set;
        }

        return $result;
    }

    /**
     * @param iterable $attributeSets
     *
     */
    private function importAttributeSetsIntoDatabase(iterable $attributeSets): void
    {
        $attributeCodes = [];

        $existingAttributeSets = $this->fetchAttributeSets();

        $entityTypes = $this->eavInfo->fetchEntityTypes();
        $setsToCreate = [];
        $groupsToCreate = [];

        $dataToImport = [];
        foreach ($attributeSets as $info) {
            $attributeCodes[$info['attribute']] = $info['attribute'];
            if (!isset($existingAttributeSets[$info['set']])) {
                $setsToCreate[] = $info['set'];
            }

            if (!isset($existingAttributeSets[$info['set']]['groups'][$info['group']])) {
                $groupsToCreate[sprintf('%s|%s', $info['set'], $info['group'])] = [$info['set'], $info['group']];
            }

            $dataToImport[] = $info;
        }

        $setsToCreate = array_unique($setsToCreate);
        $groupsToCreate = array_unique($groupsToCreate, SORT_REGULAR);

        $attributeIds = $this->fetchAttributeIds($attributeCodes);

        foreach ($setsToCreate as $set) {
            $existingAttributeSets[$set]['id'] = $this->createAttributeSet(
                ['entity_type_id' => $entityTypes['catalog_product'], 'attribute_set_name' => $set],
                $existingAttributeSets['Default']['id']
            );
        }

        $existingAttributeSets = $this->fetchAttributeSets();

        foreach ($groupsToCreate as list($set, $group)) {
            if (isset($existingAttributeSets[$set]['groups'][$group])) {
                continue;
            }

            $nextSortOrder = ($existingAttributeSets[$set]['group_sort'] ?? 0) + 10;
            $existingAttributeSets[$set]['group_sort'] = $nextSortOrder;

            $existingAttributeSets[$set]['groups'][$group] = [
                'id' => $this
                    ->createAttributeGroup(
                        $existingAttributeSets[$set]['id'],
                        [
                            'attribute_group_name' => $group,
                            'default_id' => 0,
                            'attribute_group_code' => strtolower(preg_replace('/\s+/', '-', $group)),
                            'tab_group_code' => 'basic',
                            'sort_order' => $nextSortOrder,
                        ]
                    ),
                'attributes' => [],
            ];
        }

        $attributeStatement = InsertOnDuplicate::create(
            'eav_entity_attribute',
            [
                'entity_type_id',
                'attribute_set_id',
                'attribute_group_id',
                'attribute_id',
                'sort_order',
            ]
        )
            ->onDuplicate(['sort_order'])
        ;

        foreach ($dataToImport as $row) {
            if (!isset($existingAttributeSets[$row['set']]['groups'][$row['group']]) ||
                !isset($attributeIds[$row['attribute']]) ||
                isset($existingAttributeSets[$row['set']]['groups'][$row['group']]['attributes'][$row['attribute']])) {
                continue;
            }

            $attributePositions = $existingAttributeSets[$row['set']]['groups'][$row['group']]['attributes'];

            $position = $attributePositions ? max($attributePositions) + 10 : 10;

            $existingAttributeSets[$row['set']]['groups'][$row['group']]['attributes'][$row['attribute']] = $position;

            $attributeStatement->withRow(
                $entityTypes['catalog_product'],
                $existingAttributeSets[$row['set']]['id'],
                $existingAttributeSets[$row['set']]['groups'][$row['group']]['id'],
                $attributeIds[$row['attribute']],
                $position
            );
        }

        $attributeStatement->executeIfNotEmpty($this->sql);
    }

    private function createAttributeGroup($attributeSetId, $info): int
    {
        return (int)$this->sql
            ->prepareStatementForSqlObject(
                $this->sql->insert('eav_attribute_group')
                    ->values(
                        [
                            'attribute_set_id' => $attributeSetId,
                            'attribute_group_id' => null,
                        ] + $info
                    )
            )
            ->execute()
            ->getGeneratedValue()
            ;
    }

    public function importAttributeOptions(iterable $options)
    {
        $this->transactional(
            function () use ($options) {
                $this->importAttributeOptionsIntoDatabase($options);
            }
        );
    }

    /**
     * @param iterable $options
     *
     */
    private function importAttributeOptionsIntoDatabase(iterable $options): void
    {
        $attributeIds = $this->fetchAttributeIds($this->eavInfo->fetchProductAttributes());

        $existingOptions = $this->eavInfo->fetchAttributeOptions('catalog_product', array_keys($attributeIds));

        $optionStatement = InsertOnDuplicate::create(
            'eav_attribute_option',
            [
                'option_id',
                'attribute_id',
                'sort_order',
            ]
        )
            ->onDuplicate(['sort_order'])
        ;

        foreach ($options as $option) {
            if (!isset($attributeIds[$option['attribute']])) {
                continue;
            }

            $optionId = array_search($option['option'], $existingOptions[$option['attribute']] ?? []);
            if (!$optionId) {
                $optionId = $this->sql->prepareStatementForSqlObject(
                    $this->sql->insert('eav_attribute_option')
                        ->values(['attribute_id' => $attributeIds[$option['attribute']]])
                )->execute()
                    ->getGeneratedValue()
                ;

                $this->sql->prepareStatementForSqlObject(
                    $this->sql->insert('eav_attribute_option_value')
                        ->values(['option_id' => $optionId, 'value' => $option['option'], 'store_id' => 0])
                )->execute()
                ;
            }

            $optionStatement->withRow($optionId, $attributeIds[$option['attribute']], $option['position']);
        }

        $optionStatement->executeIfNotEmpty($this->sql);
    }
}
