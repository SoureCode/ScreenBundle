<?php

namespace SoureCode\Bundle\Screen\Tests\Manager;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use SoureCode\Bundle\Screen\Factory\ScreenFactory;
use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Model\Screen;
use SoureCode\Bundle\Screen\Model\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ArrayScreenProvider;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class ScreenManagerTest extends TestCase
{
    private const int TIMEOUT_SECONDS = 5;
    private const int SLEEP_MICROSECONDS = 100;

    /**
     * @var string[] $screenNames
     */
    private array $screenNames = [];

    private function getScreenManager(string $randomEcho, int $randomSleep): ScreenManager
    {
        $factory = new ScreenFactory(Screen::class);

        $screenProvider = new ArrayScreenProvider(
            $factory,
            [
                'echoTest' => ['command' => ['echo', $randomEcho]],
                'daemonTest' => ['command' => ['sleep', $randomSleep]],
            ]
        );

        $filesystem = new Filesystem();
        $logger = new NullLogger();

        $screenManager = new ScreenManager(
            realpath(__DIR__.'/../app'),
            'test',
            $filesystem,
            $screenProvider,
            $logger,
        );

        /**
         * @var ScreenInterface[] $screens
         */
        $screens = [];
        $screens[] = $screenManager->resolveScreen('echoTest');
        $screens[] = $screenManager->resolveScreen('daemonTest');

        foreach ($screens as $screen) {
            $this->screenNames[] = $screenManager->generateScreenName($screen);
        }

        return $screenManager;
    }

    private function clearScreens(): void
    {
        foreach ($this->screenNames as $screenName) {
            $killProcess = new Process(['screen', '-S', $screenName, '-X', 'quit']);
            $killProcess->run();
        }

        $this->screenNames = [];
    }

    protected function tearDown(): void
    {
        $this->clearScreens();
    }

    public function testStartAndLogs(): void
    {
        // Arrange
        $randomEcho = bin2hex(random_bytes(10));
        $randomSleep = random_int(10, PHP_INT_MAX);
        $screenManager = $this->getScreenManager($randomEcho, $randomSleep);

        // Act
        $screenStarted = $screenManager->start('echoTest');
        $this->waitForScreenState($screenManager, 'echoTest', false, 'stop');
        $log = $screenManager->getLogs('echoTest');

        // Assert
        $this->assertTrue($screenStarted, 'Failed to start screen "echoTest".');
        $this->assertStringContainsString($randomEcho, $log, 'Expected log to contain the random text.');
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

    public function testIsRunning(): void
    {
        // Arrange
        $randomEcho = bin2hex(random_bytes(10));
        $randomSleep = random_int(10, PHP_INT_MAX);
        $screenManager = $this->getScreenManager($randomEcho, $randomSleep);

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
        $randomEcho = bin2hex(random_bytes(10));
        $randomSleep = random_int(10, PHP_INT_MAX);
        $screenManager = $this->getScreenManager($randomEcho, $randomSleep);

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
        $randomEcho = bin2hex(random_bytes(10));
        $randomSleep = random_int(10, PHP_INT_MAX);
        $screenManager = $this->getScreenManager($randomEcho, $randomSleep);

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
