<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use PHPUnit\Framework\TestCase;

class CategoryImportTest extends TestCase
{

    /** @var CategoryImport */
    private $import;

    /** @var TestDb */
    private $testDb;
    /**
     * @var CategoryInfo
     */
    private $categoryInfo;

    protected function setUp()
    {
        $this->testDb = new TestDb();

        $connection = $this->testDb->createMagentoTwoConnection();

        $this->import = CategoryImport::createFromAdapter($connection);
        $this->import->createCategoryMapTableIfNotExists();
        $this->categoryInfo = CategoryInfoFactory::createFromAdapter($connection)
            ->withStoreFilter(
                ['us_en', 'uk_en', 'eu_en']
            )
            ->withStoreMap(
                ['us_en' => 'us_en', 'uk_en' => 'uk_en', 'eu_en' => 'eu_en']
            )
            ->create();

        $this->testDb->createSnapshot($this->testDb->listTablesLike('/^(catalog_category_|url_)/'));
    }


    protected function tearDown()
    {
        $this->testDb->restoreSnapshot();
    }

    /** @test */
    public function importsBaseCategoriesIntoMainRoot()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ],
            [
                'id' => 'cat2',
                'name' => 'Category 2',
                'parent_path' => ''
            ],
            [
                'id' => 'cat3',
                'name' => 'Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->assertEquals(
            [
                'Category 1' => 3,
                'Category 2' => 4,
                'Category 3' => 5,
            ],
            $this->categoryInfo->fetchCategoryTree()
        );
    }
    
    /** @test */
    public function importsCategoryTrees()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ],
            [
                'id' => 'cat11',
                'name' => 'Category 1.1',
                'parent_path' => 'cat1',
            ],
            [
                'id' => 'cat111',
                'name' => 'Category 1.1.1',
                'parent_path' => 'cat1/cat11',
            ],
            [
                'id' => 'cat112',
                'name' => 'Category 1.1.2',
                'parent_path' => 'cat1/cat11',
            ],
            [
                'id' => 'cat12',
                'name' => 'Category 1.2',
                'parent_path' => 'cat1',
            ],
            [
                'id' => 'cat121',
                'name' => 'Category 1.2.1',
                'parent_path' => 'cat1/cat12',
            ],
            [
                'id' => 'cat2',
                'name' => 'Category 2',
                'parent_path' => ''
            ],
            [
                'id' => 'cat3',
                'name' => 'Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->assertEquals(
            [
                'Category 1' => 3,
                'Category 1 / Category 1.1' => 4,
                'Category 1 / Category 1.1 / Category 1.1.1' => 5,
                'Category 1 / Category 1.1 / Category 1.1.2' => 6,
                'Category 1 / Category 1.2' => 7,
                'Category 1 / Category 1.2 / Category 1.2.1' => 8,
                'Category 2' => 9,
                'Category 3' => 10,
            ],
            $this->categoryInfo->fetchCategoryTree()
        );
    }

    /** @test */
    public function updatesExistingCategories()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ],
            [
                'id' => 'cat2',
                'name' => 'Category 2',
                'parent_path' => ''
            ],
            [
                'id' => 'cat3',
                'name' => 'Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Updated Category 1',
                'parent_path' => 'cat2',
            ],
            [
                'id' => 'cat3',
                'name' => 'Updated Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->assertEquals(
            [
                'Category 2' => 4,
                'Category 2 / Updated Category 1' => 3,
                'Updated Category 3' => 5,
            ],
            $this->categoryInfo->fetchCategoryTree()
        );
    }

    /** @test */
    public function importsCategoryAttributesForDefaultStore()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ],
            [
                'id' => 'cat2',
                'name' => 'Category 2',
                'parent_path' => ''
            ],
            [
                'id' => 'cat3',
                'name' => 'Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->import->importCategoryAttributes([
            ['attribute' => 'name', 'value' => 'Updated Category 1', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'description', 'value' => 'Category 1 Description', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'cat2', 'store' => '']
        ]);

        $this->assertThat(
            iterator_to_array(
                $this->categoryInfo->fetchCategoryData(['name', 'description', 'include_in_menu'])
            ),
            $this->logicalAnd(
                $this->contains(['attribute' => 'name', 'value' => 'Updated Category 1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'description', 'value' => 'Category 1 Description', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'category_4', 'store' => ''])
            )
        );
    }

    /** @test */
    public function importsCategoryAttributesForCountrySpecificStores()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ]
        ]);

        $this->import->importCategoryAttributes([
            ['attribute' => 'name', 'value' => 'Category 1 UK', 'id' => 'cat1', 'store' => 'uk_en'],
            ['attribute' => 'name', 'value' => 'Category 1 EU', 'id' => 'cat1', 'store' => 'eu_en'],
            ['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'cat1', 'store' => 'us_en']
        ]);

        $this->assertThat(
            iterator_to_array(
                $this->categoryInfo->fetchCategoryData(['name', 'include_in_menu'])
            ),
            $this->logicalAnd(
                $this->contains(['attribute' => 'name', 'value' => 'Category 1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'name', 'value' => 'Category 1 EU', 'id' => 'category_3', 'store' => 'eu_en']),
                $this->contains(['attribute' => 'name', 'value' => 'Category 1 UK', 'id' => 'category_3', 'store' => 'uk_en']),
                $this->contains(['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'category_3', 'store' => 'us_en'])
            )
        );
    }

    /** @test */
    public function ignoresNonExistingStores()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ]
        ]);

        $this->import->importCategoryAttributes([
            ['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'cat1', 'store' => 'en_en'],
        ]);

        $this->assertThat(
            iterator_to_array(
                $this->categoryInfo->fetchCategoryData(['include_in_menu'])
            ),
            $this->logicalAnd(
                $this->contains(['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'category_3', 'store' => ''])
            )
        );
    }

    /** @test */
    public function ignoresNonExistingAttributes()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ]
        ]);

        $this->import->importCategoryAttributes([
            ['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'some_attribute', 'value' => '1', 'id' => 'cat1', 'store' => ''],
        ]);

        $this->assertThat(
            iterator_to_array(
                $this->categoryInfo->fetchCategoryData(['include_in_menu'])
            ),
            $this->logicalAnd(
                $this->contains(['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'category_3', 'store' => ''])
            )
        );
    }

    /** @test */
    public function ignoresNonExistingCategories()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ]
        ]);

        $this->import->importCategoryAttributes([
            ['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'cat1', 'store' => ''],
            ['attribute' => 'include_in_menu', 'value' => '1', 'id' => 'cat2', 'store' => ''],
        ]);

        $this->assertThat(
            iterator_to_array(
                $this->categoryInfo->fetchCategoryData(['include_in_menu'])
            ),
            $this->logicalAnd(
                $this->contains(['attribute' => 'include_in_menu', 'value' => '0', 'id' => 'category_3', 'store' => ''])
            )
        );
    }


    /** @test */
    public function marksAllNewlyImportedCategoriesAsActive()
    {
        $this->import->importCategories([
            [
                'id' => 'cat1',
                'name' => 'Category 1',
                'parent_path' => '',
            ],
            [
                'id' => 'cat2',
                'name' => 'Category 2',
                'parent_path' => ''
            ],
            [
                'id' => 'cat3',
                'name' => 'Category 3',
                'parent_path' => ''
            ]
        ]);

        $this->assertThat(
            iterator_to_array($this->categoryInfo->fetchCategoryData(['name', 'is_active'])),
            $this->logicalAnd(
                $this->contains(['attribute' => 'name', 'value' => 'Category 1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'is_active', 'value' => '1', 'id' => 'category_3', 'store' => '']),
                $this->contains(['attribute' => 'name', 'value' => 'Category 2', 'id' => 'category_4', 'store' => '']),
                $this->contains(['attribute' => 'is_active', 'value' => '1', 'id' => 'category_4', 'store' => '']),
                $this->contains(['attribute' => 'name', 'value' => 'Category 3', 'id' => 'category_5', 'store' => '']),
                $this->contains(['attribute' => 'is_active', 'value' => '1', 'id' => 'category_5', 'store' => ''])
            )
        );
    }
}
