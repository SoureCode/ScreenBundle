<?php

namespace SoureCode\Bundle\Screen\Tests\Provider;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Screen\SoureCodeScreenBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class DoctrineScreenProviderWithoutConfigTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->setTestProjectDir(__DIR__ . '/../app');
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestBundle(SoureCodeScreenBundle::class);
        $kernel->addTestConfig(__DIR__ . '/../app/config/config.yml');
        $kernel->addTestConfig(__DIR__ . '/../app/config/doctrine.yml');
        $kernel->addTestConfig(__DIR__ . '/../app/config/disable.yml');
        $kernel->handleOptions($options);

        return $kernel;
    }

    public function test(): void
    {
        $container = self::getContainer();

        // Assert
        $this->assertFalse($container->has('soure_code.screen.provider.doctrine'), 'The provider should not be registered.');
        $this->assertTrue($container->has('doctrine'), 'The doctrine should be registered.');
    }
}
