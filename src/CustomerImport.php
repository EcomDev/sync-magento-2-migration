<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;


use EcomDev\MagentoMigration\Sql\InsertOnDuplicate;
use EcomDev\MagentoMigration\Sql\TableResolverFactory;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;


class CustomerImport
{
    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;

    const NOT_SPECIFIED = 'Not Specified';

    const CUSTOMER_FIELDS = [
        'email', 'website_id', 'store_id', 'group_id', 'created_at', 'prefix',
        'firstname', 'middlename', 'lastname', 'suffix', 'dob', 'password_hash',
        'gender'
    ];

    const CUSTOMER_ADDRESS_FIELDS = [
        'entity_id', 'parent_id', 'prefix',
        'firstname', 'middlename', 'lastname', 'suffix',
        'company', 'street', 'region', 'region_id', 'city', 'country_id',
        'telephone', 'fax', 'postcode'
    ];

    /**
     * @var TableResolverFactory
     */
    private $resolverFactory;

    public function __construct(Sql $sql, MagentoEavInfo $eavInfo, TableResolverFactory $resolverFactory)
    {
        $this->sql = $sql;
        $this->eavInfo = $eavInfo;
        $this->resolverFactory = $resolverFactory;
    }

    public static function createFromAdapter(Adapter $connection)
    {
        return new self(
            new Sql($connection),
            MagentoEavInfo::createFromAdapter($connection),
            TableResolverFactory::createFromAdapter($connection)
        );
    }

    public function importCustomers(iterable $customers): void
    {
        $this->transactional(function () use ($customers) {
            $genderOptions = array_flip($this->eavInfo->fetchAttributeOptions('customer', ['gender'])['gender']);
            $select = $this->sql->select('customer_group')
                ->columns(['id' => 'customer_group_id', 'code' => 'customer_group_code'])
            ;

            $groupMap = [];
            foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
                $groupMap[$row['code']] = $row['id'];
            }

            $storeMap = $this->eavInfo->fetchStoreMap();

            $websiteMap = $this->eavInfo->fetchWebsiteMap($storeMap);

            $insert = InsertOnDuplicate::create('customer_entity', self::CUSTOMER_FIELDS)
                        ->onDuplicate(self::CUSTOMER_FIELDS);

            foreach ($customers as $customer) {
                $customer['website_id'] = $websiteMap[$customer['website']] ?? 0;
                $customer['store_id'] = $websiteMap[$customer['store']] ?? 0;
                $customer['group_id'] = $groupMap[$customer['group']] ?? 1;
                $customer['gender'] = $genderOptions[$customer['gender'] ?: self::NOT_SPECIFIED] ?? null;

                $insert->withAssocRow(
                    $customer
                );

                $insert = $insert->flushIfLimitReached($this->sql);
            }

            $insert->executeIfNotEmpty($this->sql);
        });
    }

    private function transactional(callable $codeBlock)
    {
        $connection = $this->sql->getAdapter()->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            $codeBlock();
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollback();
            throw $e;
        }
    }

    public function importCustomerAddresses(iterable $addresses): void
    {
        $customerResolver = $this->resolverFactory->createSingleValueResolver(
            'customer_entity',
            'email',
            'entity_id'
        );

        $streetResolver = $this->resolverFactory->createCombinedValueResolver(
            'customer_address_entity',
            'entity_id',
            'street',
            'parent_id',
            $customerResolver
        );

        $regionMap = [];

        $select = $this->sql->select('directory_country_region')
            ->columns(['country_id', 'default_name', 'region_id']);

        foreach ($this->sql->prepareStatementForSqlObject($select)->execute() as $row) {
            $regionMap[$row['country_id']][$row['default_name']] = $row['region_id'];
        }

        $insertAddress = InsertOnDuplicate::create('customer_address_entity', self::CUSTOMER_ADDRESS_FIELDS)
            ->withResolver($customerResolver)
            ->withResolver($streetResolver)
            ->onDuplicate(self::CUSTOMER_ADDRESS_FIELDS);

        $insertCustomer = InsertOnDuplicate::create('customer_entity', [
            'entity_id', 'default_billing', 'default_shipping'
        ])
            ->onDuplicate(['default_billing', 'default_shipping'])
            ->withResolver($customerResolver)
            ->withResolver($streetResolver)
        ;

        foreach ($addresses as $address) {
            $addressId = $streetResolver->unresolved([$address['street'], $address['email']]);
            $parentId = $customerResolver->unresolved($address['email']);

            $address['entity_id'] = $addressId;
            $address['parent_id'] = $parentId;
            $address['region_id'] = $regionMap[$address['country_id']][$address['region']] ?? null;
            $address['region'] = $address['region_id'] ? $address['region'] : '';

            $insertAddress->withAssocRow($address);
            $insertAddress = $insertAddress->flushIfLimitReached($this->sql);

            $insertCustomer->withRow($parentId, $addressId, $addressId)
                ->flushIfLimitReached($this->sql);
        }

        $insertAddress->executeIfNotEmpty($this->sql);
        $insertCustomer->executeIfNotEmpty($this->sql);
    }

    public function importCustomerBalance(iterable $customerBalance)
    {
        $customerResolver = $this->resolverFactory->createSingleValueResolver(
            'customer_entity',
            'email',
            'entity_id'
        );

        $storeMap = $this->eavInfo->fetchStoreMap();

        $websiteMap = $this->eavInfo->fetchWebsiteMap($storeMap);

        $insertBalance = InsertOnDuplicate::create(
                'magento_customerbalance',
                ['customer_id', 'website_id', 'amount', 'base_currency_code']
            )
            ->withResolver($customerResolver)
            ->onDuplicate(['website_id']);
        ;

        foreach ($customerBalance as $balance) {
            $insertBalance = $insertBalance->withRow(
                $customerResolver->unresolved($balance['email']),
                $websiteMap[$balance['website']],
                $balance['value'],
                $balance['currency']
            )->flushIfLimitReached($this->sql);
        }

        $insertBalance->executeIfNotEmpty($this->sql);
    }
}
