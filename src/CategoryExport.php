<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

class CategoryExport
{
    /**
     * @var CategoryInfo
     */
    private $categoryInfo;

    /**
     * @var CsvFactory
     */
    private $csvFactory;

    public function __construct(CategoryInfo $categoryInfo, CsvFactory $csvFactory)
    {

        $this->categoryInfo = $categoryInfo;
        $this->csvFactory = $csvFactory;
    }

    public function exportCategoryList(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['name', 'id', 'parent_path']);

        foreach ($this->categoryInfo->fetchMainCategoryRows() as $row) {
            $writer->write($row);
        }
    }

    public function exportCategoryData(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, ['id', 'attribute', 'store', 'value']);

        foreach ($this->categoryInfo->fetchCategoryData(
            ['name', 'description', 'meta_title', 'meta_keywords', 'meta_description', 'url_key', 'include_in_menu', 'display_mode', 'is_anchor']
        ) as $row) {
            $writer->write($row);
        }
    }
}

