<?php

namespace SoureCode\Bundle\Screen\Tests;

use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Screen\Factory\ScreenFactoryInterface;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use SoureCode\Bundle\Screen\SoureCodeScreenBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleInitializationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /**
         * @var TestKernel $kernel
         */
        $kernel = parent::createKernel($options);
        $kernel->setTestProjectDir(__DIR__ . '/app');
        $kernel->addTestBundle(SoureCodeScreenBundle::class);
        $kernel->addTestConfig(__DIR__ . '/app/config/config.yml');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function testInitBundle(): void
    {
        // Boot the kernel.
        $kernel = self::bootKernel();

        $container = $kernel->getContainer();

        $this->assertTrue($container->has(ScreenFactoryInterface::class));

        $this->assertInstanceOf(ScreenFactoryInterface::class, self::getContainer()->get('soure_code.screen.factory'));
        $this->assertInstanceOf(ScreenProviderInterface::class, self::getContainer()->get('soure_code.screen.provider.chain'));
        $this->assertInstanceOf(ScreenManager::class, self::getContainer()->get(ScreenManager::class));
    }
}
