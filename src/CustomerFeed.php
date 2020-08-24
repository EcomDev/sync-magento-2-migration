<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Combine;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\Where;

class CustomerFeed implements Feed
{
    const CUSTOMER_FIELDS = [
        'email', 'website', 'store', 'prefix',
        'firstname', 'middlename', 'lastname', 'suffix', 'password_hash',
        'group', 'created_at', 'dob', 'gender'
    ];

    const CUSTOMER_ADDRESS_FIELDS = [
        'email', 'prefix', 'firstname', 'middlename', 'lastname', 'suffix', 'company',
        'street', 'city', 'country_id', 'region', 'postcode', 'telephone', 'fax'
    ];

    const CUSTOMER_BALANCE_FIELDS = [
        'email', 'website', 'value', 'currency'
    ];

    /**
     * @var Sql
     */
    private $sql;

    /**
     * @var MagentoEavInfo
     */
    private $eavInfo;

    /**
     * @var RowMapper[]
     */
    private $rowMappers;

    public function __construct(Sql $sql, MagentoEavInfo $eavInfo, array $rowMappers)
    {
        $this->sql = $sql;
        $this->eavInfo = $eavInfo;
        $this->rowMappers = $rowMappers;
    }

    public function fetchCustomers(SelectConditionGenerator $conditionGenerator): iterable
    {
        $customerAttributeIds = $this->eavInfo->fetchAttributeIds(
            'customer',
            self::CUSTOMER_FIELDS
        );

        $attributeIdToCode = array_flip($customerAttributeIds);

        $attributeOptions = $this->eavInfo->fetchAttributeOptions(
            'customer',
            ['gender']
        );

        $storeMap = array_flip($this->eavInfo->fetchStoreMap());
        $websiteMap = [];
        foreach ($this->eavInfo->fetchWebsiteMap($this->eavInfo->fetchStoreMap()) as $code => $websiteId) {
            $websiteMap[$websiteId] = $websiteMap[$websiteId] ?? $code;
        }

        $attributeTables = [
            'customer_entity_int',
            'customer_entity_varchar',
            'customer_entity_text',
            'customer_entity_decimal',
            'customer_entity_datetime'
        ];

        foreach ($conditionGenerator->conditions() as $condition) {
            $attributeTableSelect = [];

            $mainSelect = $this->sql->select(['customer' => 'customer_entity'])
                ->join(
                    ['group' => 'customer_group'],
                    'group.customer_group_id = customer.group_id',
                    ['group' => 'customer_group_code']
                )
                ->where(function (Where $where) use ($condition) {
                    $condition->apply('customer.entity_id', $where);
                });

            foreach ($attributeTables as $table) {
                $attributeTableSelect[] = $this->sql->select(['attribute' => $table])
                        ->columns(
                            ['entity_id', 'attribute_id', 'value']
                        )
                        ->where(function (Where $where) use ($condition, $customerAttributeIds) {
                            $condition->apply('attribute.entity_id', $where);
                            $where->in('attribute.attribute_id', $customerAttributeIds);
                        });
                    ;
            }

            $customerAttributes = [];
            foreach ($this->sql->prepareStatementForSqlObject(
                new Combine($attributeTableSelect, Combine::COMBINE_UNION, Select::QUANTIFIER_ALL)
            )
                         ->execute() as $row) {
                $attributeCode = $attributeIdToCode[$row['attribute_id']];

                $customerAttributes[$row['entity_id']][$attributeCode] = $row['value'];
            }

            $defaultData = array_combine(
                self::CUSTOMER_FIELDS,
                array_fill(0, count(self::CUSTOMER_FIELDS), '')
            );

            foreach ($this->sql->prepareStatementForSqlObject($mainSelect)->execute() as $row) {
                $customer = array_replace($row, ($customerAttributes[$row['entity_id']] ?? [])) + $defaultData;
                $customer['store'] = $storeMap[$customer['store_id']] ?? '';
                $customer['website'] = $websiteMap[$customer['website_id']] ?? '';
                $customer['gender'] = $attributeOptions['gender'][$customer['gender']] ?? '';
                    $passwordHash = explode(':', $customer['password_hash'], 3);
                if (count($passwordHash) < 2) {
                    $passwordHash[] = '';
                    $passwordHash[] = '0';
                } elseif (strlen($passwordHash[0]) === 32) {
                    $passwordHash[2] = '0';
                } else {
                    $passwordHash[2] = '1';
                }

                $customer['password_hash'] = implode(':', $passwordHash);

                yield array_intersect_key($customer, $defaultData);
            }
        }
    }

    public function fetchCustomerAddresses(SelectConditionGenerator $conditionGenerator): iterable
    {
        $addressAttributeIds = $this->eavInfo->fetchAttributeIds(
            'customer_address',
            self::CUSTOMER_ADDRESS_FIELDS
        );

        $attributeIdToCode = array_flip($addressAttributeIds);

        $attributeTables = [
            'customer_address_entity_int',
            'customer_address_entity_varchar',
            'customer_address_entity_text',
            'customer_address_entity_decimal',
            'customer_address_entity_datetime'
        ];

        foreach ($conditionGenerator->conditions() as $condition) {
            $attributeTableSelect = [];

            $mainSelect = $this->sql->select(['customer' => 'customer_entity'])
                ->columns(
                    ['email']
                )
                ->join(
                    ['address' => 'customer_address_entity'],
                    'address.parent_id = customer.entity_id'
                )
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('customer.entity_id', $where);
                    }
                )
            ;

            foreach ($attributeTables as $table) {
                $attributeTableSelect[] = $this->sql->select(['attribute' => $table])
                    ->columns(
                        ['entity_id', 'attribute_id', 'value']
                    )
                    ->join(
                        ['address' => 'customer_address_entity'],
                        'address.entity_id = attribute.entity_id',
                        []
                    )
                    ->join(
                        ['customer' => 'customer_entity'],
                        'customer.entity_id = address.parent_id',
                        []
                    )
                    ->where(
                        function (Where $where) use ($condition, $addressAttributeIds) {
                            $condition->apply('customer.entity_id', $where);
                            $where->in('attribute.attribute_id', $addressAttributeIds);
                        }
                    )
                ;
            }

            $addressAttributes = [];
            foreach ($this->sql->prepareStatementForSqlObject(
                new Combine($attributeTableSelect, Combine::COMBINE_UNION, Select::QUANTIFIER_ALL)
            )->execute() as $row) {
                $attributeCode = $attributeIdToCode[$row['attribute_id']];

                $addressAttributes[$row['entity_id']][$attributeCode] = $row['value'];
            }

            $defaultData = array_combine(
                self::CUSTOMER_ADDRESS_FIELDS,
                array_fill(0, count(self::CUSTOMER_ADDRESS_FIELDS), '')
            );

            foreach ($this->sql->prepareStatementForSqlObject($mainSelect)->execute() as $row) {
                $address = array_replace($row, ($addressAttributes[$row['entity_id']] ?? [])) + $defaultData;

                yield array_intersect_key($address, $defaultData);
            }
        }
    }

    public function fetchCustomerBalance(SelectConditionGenerator $conditionGenerator): iterable
    {
        $data = $this->fetchCustomerBalanceFromDatabase($conditionGenerator);
        return isset($this->rowMappers['balance']) ? $this->rowMappers['balance']->apply($data) : $data;
    }

    private function fetchCustomerBalanceFromDatabase(SelectConditionGenerator $conditionGenerator): \Generator
    {
        $websiteMap = [];
        foreach ($this->eavInfo->fetchWebsiteMap($this->eavInfo->fetchStoreMap()) as $code => $websiteId) {
            $websiteMap[$websiteId] = $websiteMap[$websiteId] ?? $code;
        }

        $defaultData = array_combine(
            self::CUSTOMER_BALANCE_FIELDS,
            array_fill(0, count(self::CUSTOMER_BALANCE_FIELDS), '')
        );

        foreach ($conditionGenerator->conditions() as $condition) {
            $mainSelect = $this->sql->select(['customer' => 'customer_entity'])
                ->columns(
                    ['email']
                )
                ->join(
                    ['balance' => $this->eavInfo->isMagentoTwo()
                        ? 'magento_customerbalance'
                        : 'enterprise_customerbalance'],
                    'balance.customer_id = customer.entity_id',
                    ['website_id', 'value' => 'amount', 'currency' => 'base_currency_code']
                )
                ->where(
                    function (Where $where) use ($condition) {
                        $condition->apply('customer.entity_id', $where);
                    }
                )
            ;

            foreach ($this->sql->prepareStatementForSqlObject($mainSelect)->execute() as $row) {
                $row['currency'] = $row['currency'] ?? '';
                $row['website'] = $websiteMap[$row['website_id']];
                yield array_intersect_key($row, $defaultData);
            }
        }
    }
}
