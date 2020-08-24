<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


class CustomerExport
{
    /**
     * @var CsvFactory
     */
    private $csvFactory;
    /**
     * @var CustomerFeed
     */
    private $customerFeed;
    /**
     * @var SelectConditionGenerator
     */
    private $conditionGenerator;


    public function __construct(
        CustomerFeed $customerFeed,
        SelectConditionGenerator $conditionGenerator,
        CsvFactory $csvFactory
    ) {
        $this->csvFactory = $csvFactory;
        $this->customerFeed = $customerFeed;
        $this->conditionGenerator = $conditionGenerator;
    }

    public function exportCustomers(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, $this->customerFeed::CUSTOMER_FIELDS);

        foreach ($this->customerFeed->fetchCustomers($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportCustomerAddresses(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, $this->customerFeed::CUSTOMER_ADDRESS_FIELDS);

        foreach ($this->customerFeed->fetchCustomerAddresses($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }

    public function exportCustomerBalance(string $fileName)
    {
        $writer = $this->csvFactory->createWriter($fileName, $this->customerFeed::CUSTOMER_BALANCE_FIELDS);

        foreach ($this->customerFeed->fetchCustomerBalance($this->conditionGenerator) as $row) {
            $writer->write($row);
        }
    }
}
