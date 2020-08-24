<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class ProductExport
{
    /**
     * @var ProductInfo
     */
    private $productInfo;

    /**
     * @var CsvFactory
     */
    private $csvFactory;
    /**
     * @var SelectConditionGenerator
     */
    private $conditionGenerator;

    public function __construct(
        ProductInfo $productInfo,
        SelectConditionGenerator $conditionGenerator,
        CsvFactory $csvFactory
    ) {
        $this->productInfo = $productInfo;
        $this->conditionGenerator = $conditionGenerator;
        $this->csvFactory = $csvFactory;
    }

    public function exportProductList(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'type', 'set']);

        foreach ($this->productInfo->fetchProducts($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductData(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'attribute', 'store', 'value']);

        foreach ($this->productInfo->fetchProductAttributes($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductWebsite(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'store']);

        foreach ($this->productInfo->fetchProductWebsite($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductCategory(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'category', 'position']);

        foreach ($this->productInfo->fetchProductCategories($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductStock(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'stock', 'in_stock', 'qty']);

        foreach ($this->productInfo->fetchProductStock($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductImages(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'image']);

        foreach ($this->productInfo->fetchProductGallery($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductImageValues(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'image', 'store', 'label', 'position']);

        foreach ($this->productInfo->fetchProductGalleryValues($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportProductUrls(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'store', 'url']);

        foreach ($this->productInfo->fetchProductUrls($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportConfigurableAttributes(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'attribute', 'position', 'label']);

        foreach ($this->productInfo->fetchProductConfigurableAttributes($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportConfigurableRelations(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['sku', 'child_sku']);

        foreach ($this->productInfo->fetchProductConfigurableRelations($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }
}
