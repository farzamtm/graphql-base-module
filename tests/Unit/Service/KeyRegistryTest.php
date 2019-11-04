<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

declare(strict_types=1);

namespace OxidEsales\GraphQL\Base\Tests\Unit\Service;

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;
use OxidEsales\EshopCommunity\Tests\Integration\Internal\TestContainerFactory;
use OxidEsales\GraphQL\Base\Exception\NoSignatureKeyException;
use OxidEsales\GraphQL\Base\Service\KeyRegistry;
use OxidEsales\GraphQL\Base\Service\KeyRegistryInterface;
# use PHPUnit\Framework\TestCase;
use OxidEsales\TestingLibrary\UnitTestCase as TestCase;

class KeyRegistryTest extends TestCase
{
    protected static $container = null;

    protected static $keyRegistry = null;

    /**
     * this empty methods prevents phpunit from resetting
     * invocation mocker and therefore we can use the same
     * mocks for all tests and do not need to reinitialize
     * the container for every test in this file which
     * makes the whole thing pretty fast :-)
     */
    protected function verifyMockObjects()
    {
    }

    public function setUp(): void
    {
        if (self::$container !== null) {
            return;
        }

        $containerFactory = new TestContainerFactory();
        self::$container = $containerFactory->create();

        self::$container->compile();

        self::$keyRegistry = self::$container->get(KeyRegistryInterface::class);
    }

    public function tearDown(): void
    {
        Registry::set(Config::class, null);
    }

    public function testGenerateSignatureKeyCreatesRandom64BytesKeys()
    {
        $iterations = 5;
        $keys = [];
        for ($i = 0; $i < $iterations; $i++) {
            $key = self::$keyRegistry->generateSignatureKey();
            $this->assertGreaterThanOrEqual(
                64,
                strlen($key),
                'Signature key needs to be at least 64 chars, ' . strlen($key) . ' chars given'
            );
            $this->assertTrue(is_string($key), 'Signature key needs to be a string');
            $keys[] = $key;
        }
        array_unique($keys);
        $this->assertEquals(
            $iterations,
            count($keys),
            'All signature keys need to be random'
        );
    }

    public function signatureKeyProvider(): array
    {
        return [
            [true, false],
            [null, false],
            [false, false],
            [new \stdClass(), false],
            ['', false],
            ['too short', false],
            [[], false],
            ['33189b36e3fe1198cb92f49c8b6701cfd92bc58876f33361fc97bb69614c9592', true]
        ];
    }

    /**
     * @dataProvider signatureKeyProvider
     */
    public function testGetSignatureKeyWithInvalidOrNoSignature($signature, bool $valid)
    {
        $oldConfig = Registry::getConfig();
        $config = $this->getMockBuilder(Config::class)->getMock();
        $config->method('getConfigParam')
               ->with(KeyRegistry::SIGNATUREKEYNAME)
               ->willReturn($signature);
        Registry::set(Config::class, $config);
        $e = null;
        $sig = null;
        try {
            $sig = self::$keyRegistry->getSignatureKey();
        } catch (NoSignatureKeyException $e) {
        }
        if ($valid) {
            $this->assertEquals(null, $e);
            $this->assertTrue(is_string($sig), 'Signature key needs to be a string');
        } else {
            $this->assertInstanceOf(
                NoSignatureKeyException::class,
                $e
            );
        }
        Registry::set(Config::class, $oldConfig);
    }
}