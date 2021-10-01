<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class MagentoExport
{
    /**
     * @var EavMetadataExport
     */
    private $eavMetadataExport;
    /**
     * @var CategoryExport
     */
    private $categoryExport;
    /**
     * @var ProductExport
     */
    private $productExport;
    /**
     * @var CustomerExport
     */
    private $customerExport;

    public function __construct(
        EavMetadataExport $attributeExport,
        CategoryExport $categoryExport,
        ProductExport $productExport,
        CustomerExport $customerExport
    ) {
        $this->eavMetadataExport = $attributeExport;
        $this->categoryExport = $categoryExport;
        $this->productExport = $productExport;
        $this->customerExport = $customerExport;
    }

    public function exportAttributes()
    {
        $this->eavMetadataExport->exportAttributes('attribute.csv');
        $this->eavMetadataExport->exportAttributeSets('attribute_set.csv');
        $this->eavMetadataExport->exportAttributeOptions('attribute_option.csv');
    }

    public function exportCategories()
    {
        $this->categoryExport->exportCategoryList('category.csv');
        $this->categoryExport->exportCategoryData('category_data.csv');
    }

    public function exportProducts()
    {
        $this->productExport->exportProductList('product.csv');
        $this->productExport->exportProductData('product_data.csv');
        $this->productExport->exportProductWebsite('product_website.csv');
        $this->productExport->exportProductCategory('product_category.csv');
        $this->productExport->exportProductStock('stock.csv');
        $this->productExport->exportProductImages('product_image.csv');
        $this->productExport->exportProductImageValues('product_image_label.csv');
        $this->productExport->exportProductUrls('product_urls.csv');
        $this->productExport->exportConfigurableAttributes('product_configurable_attribute.csv');
        $this->productExport->exportConfigurableRelations('product_configurable_relation.csv');
    }

    public function exportProductsDataOnly()
    {
        $this->productExport->exportProductList('product.csv');
        $this->productExport->exportProductData('product_data.csv');
    }

    public function exportCustomers()
    {
        $this->customerExport->exportCustomers('customer.csv');
        $this->customerExport->exportCustomerAddresses('customer_address.csv');
        // TODO: add a check for customer balance existence and enable it back
        // $this->customerExport->exportCustomerBalance('customer_balance.csv');
    }
}
