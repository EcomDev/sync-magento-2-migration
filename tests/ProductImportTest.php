<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use PHPUnit\Framework\TestCase;

class ProductImportTest extends TestCase
{
    /** @var ProductImport */
    private $import;

    /** @var TestDb */
    private $testDb;

    /**
     * @var ProductInfo
     */
    private $productInfo;

    /** @var EavMetadataImport */
    private $eavMetadataImport;

    /** @var TableRangeConditionGeneratorFactory */
    private $conditionFactory;

    protected function setUp()
    {
        $this->testDb = new TestDb();

        $connection = $this->testDb->createMagentoTwoConnection();

        $this->import = ProductImport::createFromAdapter($connection);

        $this->productInfo = ProductInfoFactory::createFromAdapter($connection)
            ->withStoreMap(
                ['us_en' => 'us_en', 'uk_en' => 'uk_en', 'eu_en' => 'eu_en']
            )
            ->create();

        $this->eavMetadataImport = EavMetadataImport::createFromAdapter($connection);
        $this->conditionFactory = TableRangeConditionGeneratorFactory::createFromAdapter($connection);

        $this->testDb->createSnapshot(
            $this->testDb->listTablesLike('/^(eav_|catalog_|url_)/')
        );
    }


    protected function tearDown()
    {
        $this->testDb->restoreSnapshot();
    }

    /** @test */
    public function importsBasicProductData()
    {
        $this->import->importProducts([
            [
                'sku' => 'sku1',
                'type' => 'simple',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku2',
                'type' => 'configurable',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku3',
                'type' => 'bundle',
                'set' => 'Default'
            ]
        ]);

        $this->assertEquals(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'bundle',
                    'set' => 'Default'
                ]
            ],

            iterator_to_array($this->productInfo->fetchProducts($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function updatesBasicProducts()
    {
        $this->import->importProducts([
            [
                'sku' => 'sku1',
                'type' => 'simple',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku2',
                'type' => 'configurable',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku3',
                'type' => 'bundle',
                'set' => 'Default'
            ]
        ]);

        $this->import->importProducts([
            [
                'sku' => 'sku1',
                'type' => 'simple',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku2',
                'type' => 'simple',
                'set' => 'Default'
            ],
            [
                'sku' => 'sku3',
                'type' => 'simple',
                'set' => 'Default'
            ]
        ]);

        $this->assertEquals(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ],

            iterator_to_array($this->productInfo->fetchProducts($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function importsProductsWithCustomAttributeSets()
    {
        $this->eavMetadataImport->importAttributeSets(
            [
                ['set' => 'T-Shirts', 'group' => 'T-Shirt Attributes', 'attribute' => 'color']
            ]
        );

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'bundle',
                    'set' => 'Default'
                ]
            ]
        );

        $this->assertEquals(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'bundle',
                    'set' => 'Default'
                ]
            ],

            iterator_to_array($this->productInfo->fetchProducts($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function ignoresProductsWithInvalidAttributeSet()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'bundle',
                    'set' => 'Default'
                ]
            ]
        );

        $this->assertEquals(
            [
                [
                    'sku' => 'sku3',
                    'type' => 'bundle',
                    'set' => 'Default'
                ]
            ],

            iterator_to_array($this->productInfo->fetchProducts($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function importsProductAttributeValuesIntoDefaultStore()
    {
        $this->eavMetadataImport->importAttributeOptions([
            ['attribute' => 'color', 'option' => 'Red', 'position' => '1'],
            ['attribute' => 'color', 'option' => 'Green', 'position' => '2'],
            ['attribute' => 'color', 'option' => 'Blue', 'position' => '3']
        ]);

        $this->eavMetadataImport->importAttributeSets(
            [
                ['set' => 'T-Shirts', 'group' => 'T-Shirt Attributes', 'attribute' => 'color']
            ]
        );

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'T-Shirts'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'T-Shirts'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'description', 'value' => 'Some description 1', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'description', 'value' => 'Some description 2', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'color', 'value' => 'Red', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'color', 'value' => 'Blue', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'price', 'value' => '200', 'store' => ''],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000002', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'color', 'value' => 'Red', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'color', 'value' => 'Blue', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100.0000', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'description', 'value' => 'Some description 1', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'description', 'value' => 'Some description 2', 'store' => ''],

            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function setsEmptyNonTextValuesIntoNullForAttributes()
    {
        $this->eavMetadataImport->importAttributeOptions([
            ['attribute' => 'color', 'option' => 'Red', 'position' => '1'],
            ['attribute' => 'color', 'option' => 'Green', 'position' => '2'],
            ['attribute' => 'color', 'option' => 'Blue', 'position' => '3']
        ]);

        $this->eavMetadataImport->importAttributeSets(
            [
                ['set' => 'T-Shirts', 'group' => 'T-Shirt Attributes', 'attribute' => 'color']
            ]
        );

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'T-Shirts'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku2', 'attribute' => 'color', 'value' => '', 'store' => 'us_en'],
            ['sku' => 'sku2', 'attribute' => 'news_from_date', 'value' => '', 'store' => 'us_en'],
            ['sku' => 'sku2', 'attribute' => 'special_price', 'value' => '', 'store' => 'us_en'],
            ['sku' => 'sku2', 'attribute' => 'price', 'value' => '', 'store' => 'us_en'],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'color', 'value' => null, 'store' => 'us_en'],
                ['sku' => 'sku2', 'attribute' => 'price', 'value' => null, 'store' => 'us_en'],
                ['sku' => 'sku2', 'attribute' => 'special_price', 'value' => null, 'store' => 'us_en'],
                ['sku' => 'sku2', 'attribute' => 'news_from_date', 'value' => null, 'store' => 'us_en'],
            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function updatesProductAttributeValuesIntoCountryStore()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 UPDATED', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1 UK', 'store' => 'uk_en'],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 US', 'store' => 'us_en'],
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1 UK UPDATED', 'store' => 'uk_en'],
        ]);

        $this->import->importProductData([
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 US UPDATED', 'store' => 'us_en'],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000002', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1 UK UPDATED', 'store' => 'uk_en'],
                ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 UPDATED', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 US UPDATED', 'store' => 'us_en'],
            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );

    }

    /** @test */
    public function importsProductAttributeValuesIntoCountryStore()
    {

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1 UK', 'store' => 'uk_en'],
            ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 US', 'store' => 'us_en'],
            ['sku' => 'sku1', 'attribute' => 'description', 'value' => 'Some description 1', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'description', 'value' => 'Some description 2', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'price', 'value' => '300', 'store' => 'us_en'],
            ['sku' => 'sku2', 'attribute' => 'price', 'value' => '200', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'price', 'value' => '220', 'store' => 'uk_en'],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000002', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'name', 'value' => 'Sku 1 UK', 'store' => 'uk_en'],
                ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'name', 'value' => 'Sku 2 US', 'store' => 'us_en'],
                ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100.0000', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'price', 'value' => '300.0000', 'store' => 'us_en'],
                ['sku' => 'sku1', 'attribute' => 'description', 'value' => 'Some description 1', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'description', 'value' => 'Some description 2', 'store' => ''],
            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function importsProductWebsiteRelation()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductWebsite(
            [
                ['sku' => 'sku1', 'store' => 'uk_en'],
                ['sku' => 'sku2', 'store' => 'us_en']
            ]
        );

        $this->assertThat(
            iterator_to_array($this->productInfo->fetchProductWebsite($this->createProductTableCondition())),
            $this->logicalAnd(
                $this->contains(['sku' => 'sku1', 'store' => 'uk_en']),
                $this->contains(['sku' => 'sku2', 'store' => 'us_en'])
            )
        );
    }

    /** @test */
    public function importsProductMultiSelectAttributes()
    {
        $this->eavMetadataImport->importAttributes([[
            'name' => 'Color',
            'code' => 'color',
            'type' => 'varchar',
            'input' => 'multiselect',
            'scope' => 'global',
            'option' => '1',
            'default' => '',
            'unique' => 0,
            'required' => 0,
            'validation' => '',
            'searchable' => 1,
            'advanced_search' => 1,
            'layered' => 1,
            'layered_search' => 1,
            'promotion' => 1,
            'product_list' => 1,
            'product_page' => 1,
            'sortable' => 1,
            'comparable' => 1,
            'apply_to' => '',
            'html' => 1,
            'position' => 10
        ]]);

        $this->eavMetadataImport->importAttributeOptions([
            ['attribute' => 'color', 'option' => 'Red', 'position' => '1'],
            ['attribute' => 'color', 'option' => 'Green', 'position' => '2'],
            ['attribute' => 'color', 'option' => 'Blue', 'position' => '3']
        ]);

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku1', 'attribute' => 'color', 'value' => 'Red,Blue', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'color', 'value' => 'Green,Blue', 'store' => ''],
            ['sku' => 'sku3', 'attribute' => 'color', 'value' => 'Blue', 'store' => ''],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000002', 'store' => ''],
                ['sku' => 'sku3', 'attribute' => 'entity_id', 'value' => '0000000003', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'color', 'value' => 'Red,Blue', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'color', 'value' => 'Green,Blue', 'store' => ''],
                ['sku' => 'sku3', 'attribute' => 'color', 'value' => 'Blue', 'store' => ''],
            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );
    }

    /** @test */
    public function configurableProductImportIgnoresPriceAttributes()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku3',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductData([
            ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100', 'store' => ''],
            ['sku' => 'sku1', 'attribute' => 'special_price', 'value' => '80', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'price', 'value' => '90', 'store' => ''],
            ['sku' => 'sku2', 'attribute' => 'special_price', 'value' => '70', 'store' => ''],
            ['sku' => 'sku3', 'attribute' => 'price', 'value' => '10', 'store' => ''],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'attribute' => 'entity_id', 'value' => '0000000001', 'store' => ''],
                ['sku' => 'sku2', 'attribute' => 'entity_id', 'value' => '0000000002', 'store' => ''],
                ['sku' => 'sku3', 'attribute' => 'entity_id', 'value' => '0000000003', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'price', 'value' => '100.0000', 'store' => ''],
                ['sku' => 'sku1', 'attribute' => 'special_price', 'value' => '80.0000', 'store' => ''],
                ['sku' => 'sku3', 'attribute' => 'price', 'value' => '10.0000', 'store' => ''],
            ],
            iterator_to_array($this->productInfo->fetchProductAttributes($this->createProductTableCondition()))
        );
    }


    /** @test */
    public function importsProductCategoryRelation()
    {
        $categoryImport = CategoryImport::createFromAdapter($this->testDb->createMagentoTwoConnection());
        $categoryImport->importCategories([
            ['name' => 'Cat 1', 'id' => 'cat1', 'parent_path' => ''],
            ['name' => 'Cat 2', 'id' => 'cat2', 'parent_path' => ''],
            ['name' => 'Cat 4', 'id' => 'cat3', 'parent_path' => '']
        ]);

        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductCategory(
            [
                ['sku' => 'sku1', 'category' => 'cat1', 'position' => 10],
                ['sku' => 'sku1', 'category' => 'cat2', 'position' => 20],
                ['sku' => 'sku2', 'category' => 'cat2', 'position' => 40],
                ['sku' => 'sku2', 'category' => 'cat3', 'position' => 30],
            ]
        );

        $this->assertThat(
            iterator_to_array($this->productInfo->fetchProductCategories($this->createProductTableCondition())),
            $this->logicalAnd(
                $this->contains(['sku' => 'sku1', 'category' => 'category_3', 'position' => 10]),
                $this->contains(['sku' => 'sku1', 'category' => 'category_4', 'position' => 20]),
                $this->contains(['sku' => 'sku2', 'category' => 'category_4', 'position' => 40]),
                $this->contains(['sku' => 'sku2', 'category' => 'category_5', 'position' => 30])
            )
        );
    }
    
    /** @test */
    public function importsStockItems()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importStock(
            [
                ['sku' => 'sku1', 'stock' => '1', 'in_stock' => '1', 'qty' => 10],
                ['sku' => 'sku2', 'stock' => '1', 'in_stock' => '0', 'qty' => 100]
            ]
        );

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'stock' => '1', 'in_stock' => '1', 'qty' => 10],
                ['sku' => 'sku2', 'stock' => '1', 'in_stock' => '0', 'qty' => 100]
            ],
            iterator_to_array(
                $this->productInfo->fetchProductStock($this->createProductTableCondition())
            )
        );

    }

    /** @test */
    public function importBaseImageRecords()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importGallery([
            ['sku' => 'sku1', 'image' => '/image1.1.jpg'],
            ['sku' => 'sku1', 'image' => '/image1.1.jpg'],
            ['sku' => 'sku2', 'image' => '/image2.1.jpg'],
            ['sku' => 'sku2', 'image' => '/image2.2.jpg'],
            ['sku' => 'sku3', 'image' => '/image3.jpg'],
            ['sku' => 'sku4', 'image' => '/image4.jpg'],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'image' => '/image1.1.jpg'],
                ['sku' => 'sku2', 'image' => '/image2.1.jpg'],
                ['sku' => 'sku2', 'image' => '/image2.2.jpg'],
            ],

            iterator_to_array(
                $this->productInfo->fetchProductGallery(
                    $this->createProductTableCondition()
                )
            )
        );
    }

    /** @test */
    public function importFullImageRecordsWithStoreData()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importGallery([
            ['sku' => 'sku1', 'image' => '/image1.1.jpg'],
            ['sku' => 'sku2', 'image' => '/image2.1.jpg'],
            ['sku' => 'sku2', 'image' => '/image2.2.jpg']
        ]);

        $this->import->importGalleryValues([
            ['sku' => 'sku1', 'image' => '/image1.1.jpg', 'label' => '1 Default Label', 'position' => 1, 'store' => ''],
            ['sku' => 'sku1', 'image' => '/image1.1.jpg', 'label' => 'US Label', 'position' => 2, 'store' => 'us_en'],
            ['sku' => 'sku2', 'image' => '/image2.1.jpg', 'label' => '2.1 Default Label', 'position' => 1, 'store' => ''],
            ['sku' => 'sku2', 'image' => '/image2.2.jpg', 'label' => '2.2 Default Label', 'position' => 1, 'store' => ''],
            ['sku' => 'sku2', 'image' => '/image2.2.jpg', 'label' => '2.2 EU Default Label', 'position' => 3, 'store' => 'eu_en'],
        ]);

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'image' => '/image1.1.jpg', 'label' => '1 Default Label', 'position' => 1, 'store' => ''],
                ['sku' => 'sku1', 'image' => '/image1.1.jpg', 'label' => 'US Label', 'position' => 2, 'store' => 'us_en'],
                ['sku' => 'sku2', 'image' => '/image2.1.jpg', 'label' => '2.1 Default Label', 'position' => 1, 'store' => ''],
                ['sku' => 'sku2', 'image' => '/image2.2.jpg', 'label' => '2.2 Default Label', 'position' => 1, 'store' => ''],
                ['sku' => 'sku2', 'image' => '/image2.2.jpg', 'label' => '2.2 EU Default Label', 'position' => 3, 'store' => 'eu_en'],
            ],

            iterator_to_array(
                $this->productInfo->fetchProductGalleryValues(
                    $this->createProductTableCondition()
                )
            )
        );
    }

    /** @test */
    public function importsProductUrls()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductUrls(
            [
                ['sku' => 'sku1', 'url' => 'product-sku-1-us', 'store' => 'us_en'],
                ['sku' => 'sku1', 'url' => 'product-sku-1-eu', 'store' => 'eu_en'],
                ['sku' => 'sku2', 'url' => 'product-sku-2-eu', 'store' => 'eu_en']
            ]
        );

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'url' => 'product-sku-1-us.html', 'store' => 'us_en'],
                ['sku' => 'sku1', 'url' => 'product-sku-1-eu.html', 'store' => 'eu_en'],
                ['sku' => 'sku2', 'url' => 'product-sku-2-eu.html', 'store' => 'eu_en'],
            ],

            iterator_to_array(
                $this->productInfo->fetchProductUrls(
                    $this->createProductTableCondition()
                )
            )
        );
    }

    /** @test */
    public function importsConfigurableProductAttributes()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'configurable',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductConfigurableAttributes(
            [
                [
                    'sku' => 'sku1',
                    'attribute' => 'color',
                    'label' => 'Choose Color Sku1',
                    'position' => 1
                ],
                [
                    'sku' => 'sku2',
                    'attribute' => 'color',
                    'label' => 'Choose Color Sku2',
                    'position' => 1
                ]
            ]
        );

        $this->assertEquals(
            [
                [
                    'sku' => 'sku1',
                    'attribute' => 'color',
                    'label' => 'Choose Color Sku1',
                    'position' => 1
                ],
                [
                    'sku' => 'sku2',
                    'attribute' => 'color',
                    'label' => 'Choose Color Sku2',
                    'position' => 1
                ]
            ],

            iterator_to_array(
                $this->productInfo->fetchProductConfigurableAttributes(
                    $this->createProductTableCondition()
                )
            )
        );
    }

    /** @test */
    public function importsConfigurableProductRelations()
    {
        $this->import->importProducts(
            [
                [
                    'sku' => 'sku1',
                    'type' => 'configurable',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku1.1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku1.2',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku1.3',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2',
                    'type' => 'configurable',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2.1',
                    'type' => 'simple',
                    'set' => 'Default'
                ],
                [
                    'sku' => 'sku2.2',
                    'type' => 'simple',
                    'set' => 'Default'
                ]
            ]
        );

        $this->import->importProductConfigurableRelation(
            [
                ['sku' => 'sku1', 'child_sku' => 'sku1.1'],
                ['sku' => 'sku1', 'child_sku' => 'sku1.2'],
                ['sku' => 'sku1', 'child_sku' => 'sku1.3'],
                ['sku' => 'sku2', 'child_sku' => 'sku2.1'],
                ['sku' => 'sku2', 'child_sku' => 'sku2.2'],
            ]
        );

        $this->assertEquals(
            [
                ['sku' => 'sku1', 'child_sku' => 'sku1.1'],
                ['sku' => 'sku1', 'child_sku' => 'sku1.2'],
                ['sku' => 'sku1', 'child_sku' => 'sku1.3'],
                ['sku' => 'sku2', 'child_sku' => 'sku2.1'],
                ['sku' => 'sku2', 'child_sku' => 'sku2.2'],
            ],

            iterator_to_array(
                $this->productInfo->fetchProductConfigurableRelations(
                    $this->createProductTableCondition()
                )
            )
        );
    }

    private function createProductTableCondition(): SelectConditionGenerator
{
    return $this->conditionFactory->createForTable('catalog_product_entity', 'entity_id');
}
}
