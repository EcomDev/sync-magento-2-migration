<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use PHPUnit\Framework\TestCase;

class MagentoTwoEavInfoTest extends TestCase
{
    /**
     * @var MagentoEavInfo
     */
    private $info;

    protected function setUp()
    {
        $this->info = MagentoEavInfo::createFromAdapter((new TestDb())->createMagentoTwoConnection());
    }

    /** @test */
    public function fetchesMappingOfEntityTypes()
    {
        $this->assertThat(
            $this->info->fetchEntityTypes(),
            $this->logicalAnd(
                $this->arrayHasKey('catalog_product'),
                $this->arrayHasKey('catalog_category'),
                $this->arrayHasKey('order'),
                $this->arrayHasKey('invoice'),
                $this->containsOnly('int')
            )
        );
    }

    /** @test */
    public function fetchesBasicProductAttributeInformation()
    {
        $attributes = $this->info->fetchProductAttributes();

        $this->assertThat(
            $attributes,
            $this->logicalAnd(
                $this->arrayHasKey('name'),
                $this->arrayHasKey('description'),
                $this->arrayHasKey('url_key'),
                $this->contains(['name' => 'Product Name', 'code' => 'name', 'type' => 'varchar']),
                $this->contains(['name' => 'Description', 'code' => 'description', 'type' => 'text']),
                $this->contains(['name' => 'URL Key', 'code' => 'url_key', 'type' => 'varchar'])
            )
        );
    }

    /** @test */
    public function returnsExtendedAttributesInformationForStandardAttributes()
    {
        $this->assertEquals(
            [
                'name' => [
                    'scope' => 'store',
                    'input' => 'text',
                    'default' => '',
                    'option' => 0,
                    'unique' => 0,
                    'required' => 1,
                    'validation' => 'validate-length maximum-length-255',
                    'searchable' => 1,
                    'layered' => 0,
                    'layered_search' => 0,
                    'promotion' => 0,
                    'product_list' => 1,
                    'product_page' => 0,
                    'sortable' => 1,
                    'advanced_search' => 1,
                    'comparable' => 0,
                    'apply_to' => '',
                    'html' => 0,
                    'position' => 0
                ],
                'description' => [
                    'scope' => 'store',
                    'input' => 'textarea',
                    'option' => 0,
                    'default' => '',
                    'unique' => 0,
                    'required' => 0,
                    'validation' => '',
                    'searchable' => 1,
                    'layered' => 0,
                    'layered_search' => 0,
                    'promotion' => 0,
                    'product_list' => 0,
                    'product_page' => 0,
                    'sortable' => 0,
                    'advanced_search' => 1,
                    'comparable' => 1,
                    'apply_to' => '',
                    'html' => 1,
                    'position' => 0
                ]
            ],
            iterator_to_array($this->info->fetchProductAttributeConfiguration(['name', 'description']))
        );
    }

    /** @test */
    public function ignoresProductAttributesFromOutput()
    {
        $attributeCodes = array_keys($this->info->fetchProductAttributes(['name', 'description']));

        $this->assertThat($attributeCodes, $this->logicalAnd(
            $this->logicalNot($this->contains('name')),
            $this->logicalNot($this->contains('description')),
            $this->contains('url_key')
        ));
    }

    /** @test */
    public function exportsSingleSelectOptions()
    {
        $this->assertEquals(
            [
                'color' => [
                    'scope' => 'global',
                    'input' => 'select',
                    'default' => '',
                    'option' => 1,
                    'unique' => 0,
                    'required' => 0,
                    'validation' => '',
                    'searchable' => 1,
                    'layered' => 1,
                    'layered_search' => 0,
                    'promotion' => 0,
                    'product_list' => 0,
                    'product_page' => 0,
                    'sortable' => 0,
                    'advanced_search' => 1,
                    'comparable' => 1,
                    'apply_to' => 'simple,virtual,configurable',
                    'html' => 0,
                    'position' => 0,
                ]
            ],
            iterator_to_array($this->info->fetchProductAttributeConfiguration(['color']))
        );
    }

    /** @test */
    public function exportsSystemOptionAttributeAsNonOption()
    {
        $this->assertEquals(
            [
                'visibility' => [
                    'scope' => 'store',
                    'input' => 'select',
                    'default' => '4',
                    'option' => 0,
                    'unique' => 0,
                    'required' => 0,
                    'validation' => '',
                    'searchable' => 0,
                    'layered' => 0,
                    'layered_search' => 0,
                    'promotion' => 0,
                    'product_list' => 0,
                    'product_page' => 0,
                    'sortable' => 0,
                    'advanced_search' => 0,
                    'comparable' => 0,
                    'apply_to' => '',
                    'html' => 0,
                    'position' => 0
                ]
            ],
            iterator_to_array($this->info->fetchProductAttributeConfiguration(['visibility']))
        );
    }

    /** @test */
    public function fetchesMapOfStoreCodes()
    {
        $this->assertThat(
            $this->info->fetchStoreMap(),
            $this->logicalAnd(
                $this->arrayHasKey('us_en'),
                $this->arrayHasKey('eu_en'),
                $this->arrayHasKey('uk_en')
            )
        );
    }

    /** @test */
    public function fetchesDefaultAttributeSet()
    {
        $this->assertEquals(
            [
                'catalog_product' => 4,
                'catalog_category' => 3
            ],
            $this->info->fetchDefaultEntityAttributeSet('catalog_product', 'catalog_category')
        );
    }

    /** @test */
    public function detectsMagentoVersion()
    {
        $this->assertTrue($this->info->isMagentoTwo());
    }
}
