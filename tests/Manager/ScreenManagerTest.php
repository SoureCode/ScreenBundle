<?php

namespace SoureCode\Bundle\Screen\Tests\Manager;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nyholm\BundleTest\TestKernel;
use RuntimeException;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Entity\Screen;
use SoureCode\Bundle\Screen\SoureCodeScreenBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

class ScreenManagerTest extends KernelTestCase
{
    private const int TIMEOUT_SECONDS = 10;
    private const int SLEEP_MICROSECONDS = 1000;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected static function createKernel(array $options = []): KernelInterface
    {
        /** @var TestKernel $kernel */
        $kernel = parent::createKernel($options);
        $kernel->setTestProjectDir(__DIR__ . '/../app');
        $kernel->addTestBundle(SoureCodeScreenBundle::class);
        $kernel->addTestBundle(DoctrineBundle::class);
        $kernel->addTestConfig(__DIR__ . '/../app/config/config.yml');
        $kernel->addTestConfig(__DIR__ . '/../app/config/doctrine.yml');
        $kernel->handleOptions($options);

        return $kernel;
    }

    private function getScreenManager(): ScreenManager
    {
        return self::getContainer()->get(ScreenManager::class);
    }

    private function waitForScreenState(ScreenManager $screenManager, string $name, bool $expectedState, string $operation): void
    {
        $start = time();
        while ($screenManager->isRunning($name) !== $expectedState) {
            if (time() - $start > self::TIMEOUT_SECONDS) {
                throw new RuntimeException(
                    sprintf('Screen "%s" did not %s within %d seconds.', $name, $operation, self::TIMEOUT_SECONDS)
                );
            }
            usleep(self::SLEEP_MICROSECONDS);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $container = self::getContainer();

        /**
         * @var Filesystem $filesystem
         */
        $filesystem = $container->get('filesystem');
        $filesystem->mkdir(Path::join(__DIR__, '..', 'app', 'var'));

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

    public function testStartAndLogs(): void
    {
        // Arrange
        $screenManager = $this->getScreenManager();

        // Act
        $screenStarted = $screenManager->start('echoTest');
        $this->waitForScreenState($screenManager, 'echoTest', false, 'stop'); // Wait for the command to complete
        $log = $screenManager->getLogs('echoTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "echoTest".');
        $this->assertStringContainsString('Hello World', $log, 'Expected log "Hello World" not found for "echoTest".');
    }

    public function testIsRunning(): void
    {
        // Arrange
        $screenManager = $this->getScreenManager();

        // Act
        $screenStarted = $screenManager->start('daemonTest');
        $this->waitForScreenState($screenManager, 'daemonTest', true, 'start');
        $isRunning = $screenManager->isRunning('daemonTest');
        $screenStopped = $screenManager->stop('daemonTest');
        $this->waitForScreenState($screenManager, 'daemonTest', false, 'stop');
        $isRunningAfterStop = $screenManager->isRunning('daemonTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "daemonTest".');
        $this->assertTrue($isRunning, 'Screen "daemonTest" should be running.');
        $this->assertTrue($screenStopped, 'Failed to stop screen "daemonTest".');
        $this->assertFalse($isRunningAfterStop, 'Screen "daemonTest" should not be running after stop.');
    }

    public function testKill(): void
    {
        // Arrange
        $screenManager = $this->getScreenManager();

        // Act
        $screenStarted = $screenManager->start('daemonTest');
        $this->waitForScreenState($screenManager, 'daemonTest', true, 'start');
        $isRunning = $screenManager->isRunning('daemonTest');
        $screenKilled = $screenManager->kill('daemonTest');
        $isRunningAfterKill = $screenManager->isRunning('daemonTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "daemonTest".');
        $this->assertTrue($isRunning, 'Screen "daemonTest" should be running.');
        $this->assertTrue($screenKilled, 'Failed to kill screen "daemonTest".');
        $this->assertFalse($isRunningAfterKill, 'Screen "daemonTest" should not be running after kill.');
    }

    public function testStop(): void
    {
        // Arrange
        $screenManager = $this->getScreenManager();

        // Act
        $screenStarted = $screenManager->start('daemonTest');
        $this->waitForScreenState($screenManager, 'daemonTest', true, 'start');
        $isRunning = $screenManager->isRunning('daemonTest');
        $screenStopped = $screenManager->stop('daemonTest');
        $isRunningAfterStop = $screenManager->isRunning('daemonTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "daemonTest".');
        $this->assertTrue($isRunning, 'Screen "daemonTest" should be running.');
        $this->assertTrue($screenStopped, 'Failed to stop screen "daemonTest".');
        $this->assertFalse($isRunningAfterStop, 'Screen "daemonTest" should not be running after stop.');
    }
}
