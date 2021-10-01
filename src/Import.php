<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class Import
{
    /**
     * @var CsvFactory
     */
    private $csvFactory;

    /**
     * @var EavMetadataImport
     */
    private $eavMetadataImport;

    /**
     * @var CategoryImport
     */
    private $categoryImport;

    /** @var ProductImport */
    private $productImport;
    /**
     * @var CustomerImport
     */
    private $customerImport;

    public function __construct(
        CsvFactory $csvFactory,
        EavMetadataImport $eavMetadataImport,
        CategoryImport $categoryImport,
        ProductImport $productImport,
        CustomerImport $customerImport
    ) {
        $this->csvFactory = $csvFactory;
        $this->eavMetadataImport = $eavMetadataImport;
        $this->categoryImport = $categoryImport;
        $this->productImport = $productImport;
        $this->customerImport = $customerImport;
    }

    public function importAttributesOnly()
    {
        $this->eavMetadataImport->importAttributes($this->csvFactory->createReader('attribute.csv'));

        $this->eavMetadataImport->importAttributeSets($this->csvFactory->createReader('attribute_set.csv'));

        $this->eavMetadataImport->importAttributeOptions($this->csvFactory->createReader('attribute_option.csv'));
    }

    public function importAttributes()
    {
        $this->eavMetadataImport->importAttributes($this->csvFactory->createReader('attribute.csv'));

        $this->eavMetadataImport->importAttributeSets($this->csvFactory->createReader('attribute_set.csv'));

        $this->eavMetadataImport->importAttributeOptions($this->csvFactory->createReader('attribute_option.csv'));
    }

    public function importCategories()
    {
        $this->categoryImport->importCategories($this->csvFactory->createReader('category.csv'));
        $this->categoryImport->importCategoryAttributes($this->csvFactory->createReader('category_data.csv'));
    }

    public function importProductsDataOnly()
    {
        $this->productImport->importProducts($this->csvFactory->createReader('product.csv'));
        $this->productImport->importProductData($this->csvFactory->createReader('product_data.csv'));
    }

    public function importProducts()
    {
        $this->productImport->importProducts($this->csvFactory->createReader('product.csv'));

        $this->productImport->importProductData($this->csvFactory->createReader('product_data.csv'));
        $this->productImport->importProductWebsite($this->csvFactory->createReader('product_website.csv'));
        $this->productImport->importProductCategory($this->csvFactory->createReader('product_category.csv'));
        $this->productImport->importStock($this->csvFactory->createReader('stock.csv'));
        $this->productImport->importGallery($this->csvFactory->createReader('product_image.csv'));
        $this->productImport->importGalleryValues($this->csvFactory->createReader('product_image_label.csv'));

        $this->productImport->importProductUrls($this->csvFactory->createReader('product_urls.csv'));

        $this->productImport->importProductConfigurableAttributes(
            $this->csvFactory->createReader('product_configurable_attribute.csv')
        );
        $this->productImport->importProductConfigurableRelation(
            $this->csvFactory->createReader('product_configurable_relation.csv')
        );

    }

    public function importCustomers()
    {
        $this->customerImport->importCustomers($this->csvFactory->createReader('customer.csv'));
        $this->customerImport->importCustomerAddresses($this->csvFactory->createReader('customer_address.csv'));
        $this->customerImport->importCustomerBalance($this->csvFactory->createReader('customer_balance.csv'));
    }
}
