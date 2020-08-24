<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use PHPUnit\Framework\TestCase;

class EavMetadataImportTest extends TestCase
{
    /** @var MagentoEavInfo */
    private $eavInfo;

    /** @var EavMetadataImport */
    private $import;

    /** @var TestDb */
    private $testDb;

    protected function setUp()
    {
        $this->testDb = new TestDb();

        $this->testDb->createSnapshot($this->testDb->listTablesLike('/^(eav_|catalog_eav)/'));

        $connection = $this->testDb->createMagentoTwoConnection();

        $this->import = EavMetadataImport::createFromAdapter($connection);

        $this->eavInfo = MagentoEavInfo::createFromAdapter($connection);
    }

    protected function tearDown()
    {
        $this->testDb->restoreSnapshot();
    }

    /** @test */
    public function importsNewProductAttribute()
    {
        $this->import->importAttributes(
            [
                [
                    'name' => 'Base Cost',
                    'code' => 'base_cost',
                    'type' => 'decimal',
                    'input' => 'text',
                    'scope' => 'global',
                    'option' => '0',
                    'default' => '',
                    'unique' => '0',
                    'required' => '1',
                    'validation' => 'validate-zero-or-greater',
                    'searchable' => '0',
                    'advanced_search' => '0',
                    'layered' => '0',
                    'layered_search' => '0',
                    'promotion' => '1',
                    'product_list' => '0',
                    'product_page' => '0',
                    'sortable' => '0',
                    'comparable' => '0',
                    'apply_to' => '',
                    'html' => '0',
                    'position' => '2',
                ],
            ]
        );

        $actualAttribute = $this->fetchAttributeInfo('base_cost');

        $this->assertEquals(
            [
                'name' => 'Base Cost',
                'code' => 'base_cost',
                'type' => 'decimal',
                'input' => 'text',
                'scope' => 'global',
                'option' => '0',
                'default' => '',
                'unique' => '0',
                'required' => '1',
                'validation' => 'validate-zero-or-greater',
                'searchable' => '0',
                'advanced_search' => '0',
                'layered' => '0',
                'layered_search' => '0',
                'promotion' => '1',
                'product_list' => '0',
                'product_page' => '0',
                'sortable' => '0',
                'comparable' => '0',
                'apply_to' => '',
                'html' => '0',
                'position' => '2',
            ],
            $actualAttribute
        );
    }

    /** @test */
    public function updatesExistingAttribute()
    {
        $this->import->importAttributes(
            [
                [
                    'name' => 'Name',
                    'code' => 'name',
                    'type' => 'varchar',
                    'input' => 'text',
                    'scope' => 'global',
                    'option' => '0',
                    'default' => '',
                    'unique' => '0',
                    'required' => '1',
                    'validation' => 'validate-alphanum',
                    'searchable' => '0',
                    'advanced_search' => '0',
                    'layered' => '0',
                    'layered_search' => '0',
                    'promotion' => '1',
                    'product_list' => '0',
                    'product_page' => '0',
                    'sortable' => '0',
                    'comparable' => '0',
                    'apply_to' => 'simple,configurable,bundle',
                    'html' => '0',
                    'position' => '99',
                ],
            ]
        );

        $actualAttribute = $this->fetchAttributeInfo('name');

        $this->assertEquals(
            [
                'name' => 'Name',
                'code' => 'name',
                'type' => 'varchar',
                'input' => 'text',
                'scope' => 'global',
                'option' => '0',
                'default' => '',
                'unique' => '0',
                'required' => '1',
                'validation' => 'validate-alphanum',
                'searchable' => '0',
                'advanced_search' => '0',
                'layered' => '0',
                'layered_search' => '0',
                'promotion' => '1',
                'product_list' => '0',
                'product_page' => '0',
                'sortable' => '0',
                'comparable' => '0',
                'apply_to' => 'simple,configurable,bundle',
                'html' => '0',
                'position' => '99',
            ],
            $actualAttribute
        );
    }

    /** @test */
    public function importsMultipleOptionAttribute()
    {
        $this->createMultipleOptionAttributes();

        $this->assertEquals(
            [
                [
                    'name' => 'choose_length',
                    'code' => 'choose_lenght',
                    'type' => 'int',
                    'input' => 'select',
                    'scope' => 'global',
                    'option' => '1',
                    'default' => '',
                    'unique' => '0',
                    'required' => '0',
                    'validation' => '',
                    'searchable' => '0',
                    'advanced_search' => '1',
                    'layered' => '0',
                    'layered_search' => '0',
                    'promotion' => '1',
                    'product_list' => '0',
                    'product_page' => '0',
                    'sortable' => '0',
                    'comparable' => '0',
                    'apply_to' => '',
                    'html' => '0',
                    'position' => '0',
                ],
                [
                    'name' => 'Percolator Type',
                    'code' => 'choose_percolator_type',
                    'type' => 'varchar',
                    'input' => 'multiselect',
                    'scope' => 'global',
                    'option' => '1',
                    'default' => '',
                    'unique' => '0',
                    'required' => '0',
                    'validation' => '',
                    'searchable' => '0',
                    'advanced_search' => '1',
                    'layered' => '1',
                    'layered_search' => '1',
                    'promotion' => '0',
                    'product_list' => '0',
                    'product_page' => '1',
                    'sortable' => '0',
                    'comparable' => '1',
                    'apply_to' => '',
                    'html' => '0',
                    'position' => '2',
                ],
            ],
            [
                $this->fetchAttributeInfo('choose_lenght'),
                $this->fetchAttributeInfo('choose_percolator_type'),
            ]
        );
    }

    /**
     * @test
     */
    public function createsNewAttributeSetBasedOnDefaultAttributeset()
    {
        $this->createMultipleOptionAttributes();

        $this->import->importAttributeSets([
            ['set' => 'Pipe', 'group' => 'Pipe Attributes', 'attribute' => 'choose_percolator_type'],
            ['set' => 'Pipe', 'group' => 'Pipe Attributes', 'attribute' => 'choose_lenght'],
            ['set' => 'Bongs', 'group' => 'Bong Attributes', 'attribute' => 'choose_percolator_type'],
            ['set' => 'Bongs', 'group' => 'Bong Attributes', 'attribute' => 'choose_lenght'],
        ]);

        $this->assertEquals(
            [
                ['set' => 'Default', 'group' => 'Product Details', 'attribute' => 'name'],
                ['set' => 'Pipe', 'group' => 'Product Details', 'attribute' => 'name'],
                ['set' => 'Pipe', 'group' => 'Pipe Attributes', 'attribute' => 'choose_percolator_type'],
                ['set' => 'Pipe', 'group' => 'Pipe Attributes', 'attribute' => 'choose_lenght'],
                ['set' => 'Bongs', 'group' => 'Product Details', 'attribute' => 'name'],
                ['set' => 'Bongs', 'group' => 'Bong Attributes', 'attribute' => 'choose_percolator_type'],
                ['set' => 'Bongs', 'group' => 'Bong Attributes', 'attribute' => 'choose_lenght'],
            ],
            iterator_to_array($this->eavInfo->fetchAttributeSets(['name', 'choose_percolator_type', 'choose_lenght']))
        );
    }


    /**
     * @test
     */
    public function propagatesCreatedAttributeSetGroupsIntoChildAttributeSet()
    {
        $this->createMultipleOptionAttributes();

        $this->import->importAttributeSets([
            ['set' => 'Default', 'group' => 'Shipping Limitations', 'attribute' => 'choose_lenght'],
            ['set' => 'Pipe', 'group' => 'Shipping Limitations', 'attribute' => 'choose_percolator_type'],
            ['set' => 'Bongs', 'group' => 'Shipping Limitations', 'attribute' => 'choose_percolator_type']
        ]);

        $this->assertEquals(
            [
                ['set' => 'Default', 'group' => 'Shipping Limitations', 'attribute' => 'choose_lenght'],
                ['set' => 'Pipe', 'group' => 'Shipping Limitations', 'attribute' => 'choose_lenght'],
                ['set' => 'Pipe', 'group' => 'Shipping Limitations', 'attribute' => 'choose_percolator_type'],
                ['set' => 'Bongs', 'group' => 'Shipping Limitations', 'attribute' => 'choose_lenght'],
                ['set' => 'Bongs', 'group' => 'Shipping Limitations', 'attribute' => 'choose_percolator_type'],
            ],
            iterator_to_array($this->eavInfo->fetchAttributeSets(['choose_percolator_type', 'choose_lenght']))
        );
    }

    /**
     * @test
     */
    public function createsNewAttributeOptions()
    {
        $this->createMultipleOptionAttributes();

        $this->import->importAttributeOptions([
            ['attribute' => 'choose_lenght', 'option' => 'Option A', 'position' => 1],
            ['attribute' => 'choose_lenght', 'option' => 'Option B', 'position' => 2],
            ['attribute' => 'choose_percolator_type', 'option' => 'Option D', 'position' => 10],
            ['attribute' => 'choose_percolator_type', 'option' => 'Option C', 'position' => 20],
        ]);

        $attributeOptions = $this->eavInfo->fetchAttributeOptions(
            'catalog_product',
            ['choose_lenght', 'choose_percolator_type']
        );

        $this->assertEquals(
            [
                'choose_lenght' => [
                    'Option A',
                    'Option B'
                ],
                'choose_percolator_type' => [
                    'Option D',
                    'Option C'
                ]
            ],
            [
                'choose_lenght' => array_values($attributeOptions['choose_lenght']),
                'choose_percolator_type' => array_values($attributeOptions['choose_percolator_type']),
            ]
        );
    }


    private function fetchAttributeInfo(string $attributeCode): array
    {
        $actualAttribute =
            $this->eavInfo->fetchProductAttributes()[$attributeCode]
            + current(iterator_to_array($this->eavInfo->fetchProductAttributeConfiguration([$attributeCode])));

        return $actualAttribute;
    }

    private function createMultipleOptionAttributes(): void
    {
        $this->import->importAttributes(
            [
                [
                    'name' => 'choose_length',
                    'code' => 'choose_lenght',
                    'type' => 'int',
                    'input' => 'select',
                    'scope' => 'global',
                    'option' => '1',
                    'default' => '',
                    'unique' => '0',
                    'required' => '0',
                    'validation' => '',
                    'searchable' => '0',
                    'advanced_search' => '1',
                    'layered' => '0',
                    'layered_search' => '0',
                    'promotion' => '1',
                    'product_list' => '0',
                    'product_page' => '0',
                    'sortable' => '0',
                    'comparable' => '0',
                    'apply_to' => '',
                    'html' => '0',
                    'position' => '0',
                ],
                [
                    'name' => 'Percolator Type',
                    'code' => 'choose_percolator_type',
                    'type' => 'varchar',
                    'input' => 'multiselect',
                    'scope' => 'global',
                    'option' => '1',
                    'default' => '',
                    'unique' => '0',
                    'required' => '0',
                    'validation' => '',
                    'searchable' => '0',
                    'advanced_search' => '1',
                    'layered' => '1',
                    'layered_search' => '1',
                    'promotion' => '0',
                    'product_list' => '0',
                    'product_page' => '1',
                    'sortable' => '0',
                    'comparable' => '1',
                    'apply_to' => '',
                    'html' => '0',
                    'position' => '2',
                ],
            ]
        );
    }

}
