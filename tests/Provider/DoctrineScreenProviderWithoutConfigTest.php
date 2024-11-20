<?php

namespace SoureCode\Bundle\Screen\Tests\Provider;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Screen\Model\Screen;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use SoureCode\Bundle\Screen\SoureCodeScreenBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class DoctrineScreenProviderWithoutConfigTest extends KernelTestCase
{
    private ?ScreenProviderInterface $provider = null;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestBundle(SoureCodeScreenBundle::class);
        $kernel->addTestConfig(__DIR__ . '/../app/config/config.yml');
        $kernel->addTestConfig(__DIR__ . '/../app/config/doctrine_empty.yml');
        $kernel->handleOptions($options);

        return $kernel;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->provider = $container->get('soure_code.screen.provider.doctrine');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->getConnection()->close();
        $this->entityManager->clear();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    public function test(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mapping not configured.');

        // Act
        $exists = $this->provider->has('testABC');
        $all = $this->provider->all();

        // Assert
        $this->assertFalse($exists, 'The provider should not be able to check if a screen exists.');
        $this->assertEmpty($all, 'The provider should not return any screens.');

        // Act
        $this->provider->get('testCBA');
    }
}
