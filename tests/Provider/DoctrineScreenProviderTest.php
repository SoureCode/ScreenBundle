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

class DoctrineScreenProviderTest extends KernelTestCase
{
    private ?EntityManagerInterface $entityManager = null;
    private ?EntityRepository $repository = null;
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
        $kernel->addTestConfig(__DIR__ . '/../app/config/doctrine.yml');
        $kernel->handleOptions($options);

        return $kernel;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->provider = $container->get('soure_code.screen.provider.doctrine');
        $this->repository = $this->entityManager->getRepository(Screen::class);

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema([
            $this->entityManager->getClassMetadata(Screen::class),
        ]);

        $this->repository->createQueryBuilder('screen')
            ->delete()
            ->getQuery()
            ->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();

        $this->entityManager->getConnection()->close();
        $this->entityManager->clear();
        $this->entityManager->close();

        $this->repository = null;
        $this->entityManager = null;
    }

    public function testHas(): void
    {
        // Arrange
        $screen = new Screen('testScreen', ['echo', 'Hello World']);
        $this->entityManager->persist($screen);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act
        $exists = $this->provider->has('testScreen');
        $nonExistent = $this->provider->has('nonExistentScreen');

        // Assert
        $this->assertTrue($exists, 'The provider should report "testScreen" as existing.');
        $this->assertFalse($nonExistent, 'The provider should report "nonExistentScreen" as not existing.');
    }

    public function testGet(): void
    {
        // Arrange
        $screen = new Screen('testScreen', ['echo', 'Hello World']);
        $this->entityManager->persist($screen);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act
        $retrievedScreen = $this->provider->get('testScreen');

        // Assert
        $this->assertNotNull($retrievedScreen, 'The provider should retrieve the screen "testScreen".');
        $this->assertEquals('testScreen', $retrievedScreen->getName(), 'The retrieved screen name should match "testScreen".');
        $this->assertEquals(['echo', 'Hello World'], $retrievedScreen->getCommand(), 'The command should match the one set for "testScreen".');
    }

    public function testAll(): void
    {
        // Arrange
        $screen1 = new Screen('screen1', ['echo', 'Screen 1']);
        $screen2 = new Screen('screen2', ['echo', 'Screen 2']);
        $this->entityManager->persist($screen1);
        $this->entityManager->persist($screen2);
        $this->entityManager->flush();
        $this->entityManager->clear();

        // Act
        $allScreens = $this->provider->all();

        // Assert
        $this->assertCount(2, $allScreens, 'The provider should return exactly 2 screens.');
        $this->assertArrayHasKey('screen1', $allScreens, 'The provider should return "screen1".');
        $this->assertArrayHasKey('screen2', $allScreens, 'The provider should return "screen2".');
        $this->assertEquals('screen1', $allScreens['screen1']->getName(), 'The name of "screen1" should match.');
        $this->assertEquals('screen2', $allScreens['screen2']->getName(), 'The name of "screen2" should match.');
    }
}
