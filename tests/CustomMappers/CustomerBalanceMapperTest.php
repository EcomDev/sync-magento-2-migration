<?php
/**
 * Copyright © EcomDev B.V. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace EcomDev\MagentoMigration\CustomMappers;


use PHPUnit\Framework\TestCase;

class CustomerBalanceMapperTest extends TestCase
{
    /** @var CustomerBalanceMapper */
    private $mapper;

    protected function setUp()
    {
        $this->mapper = new CustomerBalanceMapper();
    }



    /** @test */
    public function withoutMergingConfigurationReturnsDataAsIs()
    {
        $this->assertEquals(
            [
                [
                    'email' => 'customer@name.nl',
                    'website' => 'int_en',
                    'currency' => '',
                    'value' => '120.0000',
                ],
                [
                    'email' => 'customer@name.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => '11.0653',
                ],
                [
                    'email' => 'customer@name.nl',
                    'website' => 'us_en',
                    'currency' => '',
                    'value' => '0.0000',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'int_en',
                    'currency' => '',
                    'value' => '50.3600',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => '55.7931',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'us_en',
                    'currency' => '',
                    'value' => '31.2600',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'eu_en',
                    'currency' => '',
                    'value' => '0.0000',
                ],
            ],

            iterator_to_array(
                $this->mapper->apply(
                    [
                        [
                            'email' => 'customer@name.nl',
                            'website' => 'int_en',
                            'currency' => '',
                            'value' => '120.0000',
                        ],
                        [
                            'email' => 'customer@name.nl',
                            'website' => 'uk_en',
                            'currency' => '',
                            'value' => '11.0653',
                        ],
                        [
                            'email' => 'customer@name.nl',
                            'website' => 'us_en',
                            'currency' => '',
                            'value' => '0.0000',
                        ],
                        [
                            'email' => 'another_customer@website.nl',
                            'website' => 'int_en',
                            'currency' => '',
                            'value' => '50.3600',
                        ],
                        [
                            'email' => 'another_customer@website.nl',
                            'website' => 'uk_en',
                            'currency' => '',
                            'value' => '55.7931',
                        ],
                        [
                            'email' => 'another_customer@website.nl',
                            'website' => 'us_en',
                            'currency' => '',
                            'value' => '31.2600',
                        ],
                        [
                            'email' => 'another_customer@website.nl',
                            'website' => 'eu_en',
                            'currency' => '',
                            'value' => '0.0000',
                        ],
                    ]
                )
            )
        );
    }

    /** @test */
    public function mergesWebsiteIntoTargetOne()
    {
        $this->assertEquals(
            [
                [
                    'email' => 'customer@name.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => '11.0653',
                ],
                [
                    'email' => 'customer@name.nl',
                    'website' => 'us_en',
                    'currency' => '',
                    'value' => 0.00 + 120.00,
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => '55.7931',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'us_en',
                    'currency' => '',
                    'value' => 31.26 + 50.36,
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'eu_en',
                    'currency' => '',
                    'value' => '0.0000',
                ],
            ],

            iterator_to_array(
                $this->mapper->withMerge('int_en', 'us_en')
                    ->apply(
                        [
                            [
                                'email' => 'customer@name.nl',
                                'website' => 'int_en',
                                'currency' => '',
                                'value' => '120.0000',
                            ],
                            [
                                'email' => 'customer@name.nl',
                                'website' => 'uk_en',
                                'currency' => '',
                                'value' => '11.0653',
                            ],
                            [
                                'email' => 'customer@name.nl',
                                'website' => 'us_en',
                                'currency' => '',
                                'value' => '0.0000',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'int_en',
                                'currency' => '',
                                'value' => '50.3600',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'uk_en',
                                'currency' => '',
                                'value' => '55.7931',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'us_en',
                                'currency' => '',
                                'value' => '31.2600',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'eu_en',
                                'currency' => '',
                                'value' => '0.0000',
                            ],
                        ]
                    )
            )
        );
    }

    /** @test */
    public function convertsValuesForSpecifiedStoreRates()
    {
        $this->assertEquals(
            [
                [
                    'email' => 'customer@name.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => 7.7,
                ],
                [
                    'email' => 'customer@name.nl',
                    'website' => 'us_en',
                    'currency' => '',
                    'value' => '120.0000',
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'uk_en',
                    'currency' => '',
                    'value' => 77,
                ],
                [
                    'email' => 'another_customer@website.nl',
                    'website' => 'eu_en',
                    'currency' => '',
                    'value' => 41.5,
                ],
            ],

            iterator_to_array(
                $this->mapper->withRate('eu_en', 0.83)
                    ->withRate('uk_en', 0.77)
                    ->apply(
                        [
                            [
                                'email' => 'customer@name.nl',
                                'website' => 'uk_en',
                                'currency' => '',
                                'value' => '10',
                            ],
                            [
                                'email' => 'customer@name.nl',
                                'website' => 'us_en',
                                'currency' => '',
                                'value' => '120.0000',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'uk_en',
                                'currency' => '',
                                'value' => '100',
                            ],
                            [
                                'email' => 'another_customer@website.nl',
                                'website' => 'eu_en',
                                'currency' => '',
                                'value' => '50',
                            ],
                        ]
                    )
            )
        );
    }
}
