<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class EavMetadataExport
{
    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;
    /**
     * @var CsvFactory
     */
    private $csvFactory;
    /**
     * @var array
     */
    private $attributes;

    public function __construct(MagentoEavInfo $eavInfo, CsvFactory $csvFactory)
    {
        $this->eavInfo = $eavInfo;
        $this->csvFactory = $csvFactory;
    }

    public function exportAttributes(string $targetFile)
    {
        $writer = $this->csvFactory->createWriter($targetFile, [
            'name',
            'code',
            'type',
            'input',
            'scope',
            'option',
            'default',
            'unique',
            'required',
            'validation',
            'searchable',
            'advanced_search',
            'layered',
            'layered_search',
            'promotion',
            'product_list',
            'product_page',
            'sortable',
            'comparable',
            'apply_to',
            'html',
            'position',
        ]);

        $attributes = $this->fetchAttributes();
        $attributeCodes = array_keys($attributes);

        foreach ($this->eavInfo->fetchProductAttributeConfiguration($attributeCodes) as $code => $attribute) {
            $writer->write($attribute + $attributes[$code]);
        }
    }

    public function exportAttributeSets(string $targetFile)
    {
        $attributeCodes = array_keys($this->fetchAttributes());
        $writer = $this->csvFactory->createWriter($targetFile, ['set', 'group', 'attribute']);

        foreach ($this->eavInfo->fetchAttributeSets($attributeCodes) as $row) {
            $writer->write($row);
        }
    }

    /**
     *
     * @return array
     */
    private function fetchAttributes(): array
    {
        if (!$this->attributes) {
            $this->attributes = $this->eavInfo->fetchProductAttributes(['media_gallery']);
        }

        return $this->attributes;
    }

    public function exportAttributeOptions(string $targetFile)
    {
        $writer = $this->csvFactory->createWriter($targetFile, ['attribute', 'option', 'position']);

        $attributeCodes = array_keys($this->fetchAttributes());

        foreach ($this->eavInfo
                     ->fetchAttributeOptions('catalog_product', $attributeCodes) as $attributeCode => $options) {

            $position = 1;
            foreach ($options as $option) {
                $writer->write([
                    'attribute' => $attributeCode,
                    'option' => $option,
                    'position' => $position ++
                ]);
            }
        }

    }
}
