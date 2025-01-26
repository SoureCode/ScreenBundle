<?php


use Nyholm\BundleTest\TestKernel;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\SoureCodeScreenBundle;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

class EventTest extends KernelTestCase
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
        $kernel->setTestProjectDir(__DIR__ . '/app');
        $kernel->addTestBundle(SoureCodeScreenBundle::class);
        $kernel->addTestConfig(__DIR__ . '/app/config/config.yml');
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

    public function testRestart(): void
    {
        // Arrange
        $screenManager = $this->getScreenManager();

        // Act
        $screenStarted = $screenManager->start('restartTest');
        $this->waitForScreenState($screenManager, 'restartTest', false, 'stop');
        $this->waitForScreenState($screenManager, 'restartTest', true, 'start');
        $this->waitForScreenState($screenManager, 'restartTest', false, 'stop');
        $this->waitForScreenState($screenManager, 'restartTest', true, 'start');

        $screenManager->gracefullyStop('restartTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "restartTest".');
    }
}
