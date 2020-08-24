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
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate\Predicate;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\TableIdentifier;
use Zend\Db\Sql\Where;

class MagentoEavInfo
{
    /**
     * @var Sql
     */
    private $sql;

    /** @var array */
    private $magentoOneTableExists;

    private $standardAttributes = [
        'image', 'small_image', 'thumbnail', 'custom_design', 'custom_design_from',
        'custom_design_to',
        'custom_layout',
        'custom_layout_update',
        'gallery',
        'page_layout',
        'swatch_image'
    ];
    /**
     * @var MetadataInterface
     */
    private $metadata;

    public function __construct(Sql $sql, MetadataInterface $metadata)
    {
        $this->sql = $sql;
        $this->metadata = $metadata;
    }

    public static function createFromAdapter(Adapter $adapter): self
    {
        return new self(new Sql($adapter), Factory::createSourceFromAdapter($adapter));
    }

    public function fetchEntityTypes(): array
    {
        $select = $this->sql->select();

        $select->from('eav_entity_type')
            ->columns(['id' => 'entity_type_id', 'type' => 'entity_type_code']);

        $result = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['type']] = (int)$row['id'];
        }

        return $result;
    }

    public function fetchProductAttributes(array $ignoredAttributes = [])
    {
        $ignoredAttributes = array_merge($ignoredAttributes, $this->standardAttributes);
        $select = $this->sql->select()
            ->from(['attribute' => 'eav_attribute'])
            ->columns(
                [
                    'name' => 'frontend_label',
                    'code' => 'attribute_code',
                    'type' => 'backend_type'
                ]
            )
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->join(
                ['catalog_attribute' => 'catalog_eav_attribute'],
                'attribute.attribute_id = catalog_attribute.attribute_id',
                []
            )
            ->where(
                function (Where $where) use ($ignoredAttributes) {
                    $where->equalTo('entity_type.entity_type_code', 'catalog_product');
                    $where->notEqualTo('attribute.backend_type', 'static');
                    $where->equalTo('catalog_attribute.is_visible', true);

                    $where->notIn('attribute.attribute_code', $ignoredAttributes);
                }
            );

        $result = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['code']] = $row;
        }

        return $result;
    }

    public function fetchAllProductAttributeIds()
    {
        $select = $this->sql->select()
            ->from(['attribute' => 'eav_attribute'])
            ->columns(
                [
                    'code' => 'attribute_code',
                    'id' => 'attribute_id'
                ]
            )
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->where(
                function (Where $where) {
                    $where->equalTo('entity_type.entity_type_code', 'catalog_product');
                }
            );

        $result = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['code']] = (int)$row['id'];
        }

        return $result;
    }


    public function fetchProductAttributeConfiguration(array $attributeCodes): iterable
    {
        $select = $this->sql->select()
            ->from(['attribute' => 'eav_attribute'])
            ->columns(['*'])
            ->join(
                ['catalog_attribute' => 'catalog_eav_attribute'],
                'attribute.attribute_id = catalog_attribute.attribute_id',
                ['*']
            )
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->where(
                function (Where $where) use ($attributeCodes) {
                    $where->equalTo('entity_type.entity_type_code', 'catalog_product');
                    $where->in('attribute.attribute_code', $attributeCodes);
                }
            );

        $scopes = [1 => 'global', 0 => 'store', 2=>'website'];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            yield $row['attribute_code'] => [
                'input' => $row['frontend_input'],
                'scope' => $scopes[$row['is_global']],
                'option' => $this->isOptionAttribute($row) ? 1 : 0,
                'default' => $row['default_value'] ?? '',
                'unique' => (int)$row['is_unique'],
                'required' => (int)$row['is_required'],
                'validation' => $row['frontend_class'] ?? '',
                'searchable' => (int)$row['is_searchable'],
                'advanced_search' => (int)$row['is_visible_in_advanced_search'],
                'layered' => (int)$row['is_filterable'],
                'layered_search' => (int)$row['is_filterable_in_search'],
                'promotion' => (int)$row['is_used_for_price_rules'],
                'product_list' => (int)$row['used_in_product_listing'],
                'product_page' => (int)$row['is_visible_on_front'],
                'sortable' => (int)$row['used_for_sort_by'],
                'comparable' => (int)$row['is_comparable'],
                'apply_to' => $row['apply_to'] ?? '',
                'html' => (int)$row['is_html_allowed_on_front'],
                'position' => (int)$row['position']
            ];
        }
    }

    private function isOptionAttribute($row): bool
    {
        if (in_array($row['frontend_input'], ['select', 'swatch_visual', 'swatch_text', 'multiselect'])) {
            $row['source_model'];
            return in_array($row['backend_type'], ['int', 'varchar'])
                && in_array($row['source_model'], [
                    'eav/entity_attribute_source_table',
                    null,
                    'Magento\Eav\Model\Entity\Attribute\Source\Table'
                ]);
        }

        return false;
    }

    public function fetchAttributeSets(array $attributeCodes): iterable
    {
        $select = $this->sql->select()
            ->from(['attribute_relation' => 'eav_entity_attribute'])
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute_relation.entity_type_id',
                []
            )
            ->columns([])
            ->join(
                ['attribute_set' => 'eav_attribute_set'],
                'attribute_set.attribute_set_id = attribute_relation.attribute_set_id',
                ['set' => 'attribute_set_name']
            )
            ->join(
                ['attribute' => 'eav_attribute'],
                'attribute_relation.attribute_id = attribute.attribute_id',
                ['attribute' => 'attribute_code']
            )
            ->join(
                ['attribute_group' => 'eav_attribute_group'],
                'attribute_group.attribute_group_id = attribute_relation.attribute_group_id',
                ['group' => 'attribute_group_name']
            )
            ->order(new Expression('(attribute_set_name = ?) DESC', 'Default'))
            ->order('attribute_set.attribute_set_id ASC')
            ->order('attribute_group.sort_order ASC')
            ->order('attribute_relation.sort_order ASC')

            ->where(
                function (Where $where) use ($attributeCodes) {
                    $where->equalTo('entity_type.entity_type_code', 'catalog_product');
                    $where->in('attribute.attribute_code', $attributeCodes);
                }
            );

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            yield $row;
        }
    }

    public function fetchStoreMap(): array
    {

        $select = $this->sql->select($this->isMagentoTwo() ? 'store' : 'core_store')
            ->columns(['code', 'store_id'])
            ->where('store_id > 0');

        $map = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $map[$row['code']] = $row['store_id'];
        }

        return $map;
    }

    public function fetchWebsiteMap($storeMap): array
    {
        if (!$storeMap) {
            return [];
        }

        $select = $this->sql->select($this->isMagentoTwo() ? 'store' : 'core_store')
            ->columns(['code', 'website_id'])
            ->where(function (Where $where) use ($storeMap) {
                $where->in('code', array_keys($storeMap));
            });

        $map = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $map[$row['code']] = $row['website_id'];
        }

        return $map;
    }

    public function fetchAttributeOptions(string $entityType, array $attributeCodes): array
    {
        $select = $this->sql->select()
            ->from(['attribute' => 'eav_attribute'])
            ->columns(['attribute_code'])
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->join(
                ['option' => 'eav_attribute_option'],
                'option.attribute_id = attribute.attribute_id',
                ['option_id']
            )
            ->join(
                ['option_value' => 'eav_attribute_option_value'],
                'option_value.option_id = option.option_id',
                ['value']
            )
            ->where(
                function (Where $where) use ($attributeCodes, $entityType) {
                    $where->equalTo('entity_type.entity_type_code', $entityType);
                    $where->equalTo('option_value.store_id', 0);
                    $where->in('attribute.attribute_code', $attributeCodes);
                }
            )
            ->order('option.sort_order ASC')
            ->order('option_value.value ASC')
        ;

        $result = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['attribute_code']][$row['option_id']] = $row['value'];
        }

        return $result;
    }

    public function isMagentoTwo(): bool
    {
        if (!isset($this->magentoOneTableExists)) {
            $this->magentoOneTableExists = array_intersect(
                ['core_resource', 'core_url_rewrite'],
                $this->metadata->getTableNames()
            );
        }

        return empty($this->magentoOneTableExists);
    }

    public function fetchAttributeIds(string $entityType, array $attributeCodes): array
    {
        $select = $this->sql->select()
            ->from(['attribute' => 'eav_attribute'])
            ->columns(['attribute_code', 'attribute_id'])
            ->join(
                ['entity_type' => 'eav_entity_type'],
                'entity_type.entity_type_id = attribute.entity_type_id',
                []
            )
            ->where(
                function (Where $where) use ($attributeCodes, $entityType) {
                    $where->equalTo('entity_type.entity_type_code', $entityType);
                    $where->in('attribute.attribute_code', $attributeCodes);
                }
            )
        ;

        $result = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['attribute_code']] = (int)$row['attribute_id'];
        }

        return $result;
    }

    public function fetchDefaultEntityAttributeSet(string ...$entityTypes): array
    {
        $select = $this->sql->select()
            ->from('eav_entity_type')
            ->columns(['entity_type_code', 'default_attribute_set_id'])
            ->where(
                function (Where $where) use ($entityTypes) {
                    $where->in('entity_type_code', $entityTypes);
                }
            )
        ;

        $result = [];

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $result[$row['entity_type_code']] = (int)$row['default_attribute_set_id'];
        }
        return $result;
    }

    public function fetchAttributeSetMap(string $entityType): array
    {
        $entityTypeId = $this->fetchEntityTypes()[$entityType];

        $select = $this->sql->select('eav_attribute_set')
            ->columns(['id' => 'attribute_set_id', 'name' => 'attribute_set_name'])
            ->where(function (Where $where) use ($entityTypeId) {
                $where->equalTo('entity_type_id', $entityTypeId);
            });

        $results = [];
        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $results[$row['id']] = $row['name'];
        }

        return $results;
    }
}
