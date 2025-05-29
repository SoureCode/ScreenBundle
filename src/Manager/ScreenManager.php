<?php

namespace SoureCode\Bundle\Screen\Manager;

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Screen\Model\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

readonly class ScreenManager
{
    public function __construct(
        private string $baseDirectory,
        private string $environment,
        private Filesystem $filesystem,
        private ScreenProviderInterface $provider,
        private LoggerInterface $logger,
    ) {
    }

    public function start(ScreenInterface|string $nameOrScreen): bool
    {
        $screen = $this->resolveScreen($nameOrScreen);

        if ($this->isRunning($nameOrScreen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        $logDir = $this->getLogDirectory();

        if (!$this->filesystem->exists($logDir)) {
            $this->filesystem->mkdir($logDir);
        }

        $logFile = $this->getLogFile($screenName);

        $this->filesystem->remove($logFile);

        $this->logger->info(\sprintf('Starting screen "%s"', $screen->getName()), [
            'screen' => $screen->getName(),
            'command' => $screen->getCommand(),
            'environment' => $this->environment,
        ]);

        $escapedCommand = implode(' ', array_map('escapeshellarg', $screen->getCommand()));

        $shell = getenv('SHELL');

        if (!$shell || !is_executable($shell)) {
            $shell = '/bin/sh';
        }

        $process = new Process([
            'screen',
            '-L',
            '-Logfile',
            $logFile,
            '-dmS',
            $screenName,
            $shell,
            '-c',
            $escapedCommand,
        ], $this->getBaseDirectory(),
            [
                'APP_ENV' => $this->environment,
            ],
            null,
            5
        );

        $process->run();

        $this->logger->info(\sprintf('Screen "%s" started', $screen->getName()), [
            'screen' => $screen->getName(),
            'command' => $screen->getCommand(),
            'environment' => $this->environment,
            'output' => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
            'exitCode' => $process->getExitCode(),
        ]);

        return $process->isSuccessful();
    }

    protected function resolveScreen(ScreenInterface|string $nameOrScreen): ScreenInterface
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            return $this->provider->get($nameOrScreen);
        }

        return $nameOrScreen;
    }

    /**
     * @phpstan-impure
     */
    public function isRunning(ScreenInterface|string $nameOrScreen): bool
    {
        $screen = $this->resolveScreen($nameOrScreen);
        $screenName = $this->generateScreenName($screen);

        $process = new Process(['screen', '-ls']);

        $process->run();

        return str_contains($process->getOutput(), $screenName);
    }

    private function generateScreenName(ScreenInterface $screen): string
    {
        return hash('sha256', \sprintf('%s%s%s', $this->getBaseDirectory(), $screen->getName(), implode('', $screen->getCommand())));
    }

    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }

    private function getLogDirectory(): string
    {
        return Path::join($this->getBaseDirectory(), 'var', 'log');
    }

    private function getLogFile(string $screenName): string
    {
        return Path::join($this->getLogDirectory(), \sprintf('screen-%s.log', $screenName));
    }

    public function phpBinary(): string
    {
        $finder = new PhpExecutableFinder();
        $phpBinary = $finder->find(false);

        if ($phpBinary) {
            return $phpBinary;
        }

        throw new \RuntimeException('PHP binary not found.');
    }

    public function consoleBinary(): string
    {
        return Path::join($this->getBaseDirectory(), 'bin', 'console');
    }

    /**
     * @param int $timeout - Time to wait in seconds for the screen to kill after trying to gracefully stop it
     * @param int $sleep   - Time to sleep in microseconds between checks
     */
    public function gracefullyStop(ScreenInterface|string $nameOrScreen, int $timeout = 5, int $sleep = 1000): bool
    {
        $screen = $this->resolveScreen($nameOrScreen);

        if (!$this->isRunning($screen)) {
            return true;
        }

        // Attempt a graceful stop first
        if ($this->stop($screen)) {
            $start = time();

            // Wait for the screen to stop
            while ($this->isRunning($screen)) {
                if (time() - $start > $timeout) {
                    break;
                }

                usleep($sleep);
            }

            return $this->kill($screen);
        }

        return true;
    }

    public function stop(ScreenInterface|string $nameOrScreen): bool
    {
        $screen = $this->resolveScreen($nameOrScreen);

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        // Attempt a graceful quit by sending ctrl+c
        $process = new Process(['screen', '-S', $screenName, '-X', 'stuff', "\003"]);

        $process->run();

        return $process->isSuccessful();
    }

    public function kill(ScreenInterface|string $nameOrScreen): bool
    {
        $screen = $this->resolveScreen($nameOrScreen);

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        $pid = $this->getPid($screenName);

        $screenKillProcess = new Process(['screen', '-S', $screenName, '-X', 'kill']);
        $screenKillProcess->run();

        if ($this->isRunning($screen)) {
            $killProcess = new Process(['kill', '-9', $pid]);
            $killProcess->run();

            $wipeProcess = new Process(['screen', '-wipe']);
            $wipeProcess->run();

            return $screenKillProcess->isSuccessful() && $killProcess->isSuccessful() && $wipeProcess->isSuccessful();
        }

        return $screenKillProcess->isSuccessful();
    }

    private function getPid(string $screenName): string
    {
        $process = new Process(['screen', '-ls']);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Failed to list screen sessions.');
        }

        $output = $process->getOutput();

        preg_match('/\d+\.'.preg_quote($screenName, '/').'\s+\(([^)]+)\)/', $output, $matches);

        if (isset($matches[1])) {
            return $matches[1];
        }

        throw new \RuntimeException(\sprintf('Screen session "%s" not found.', $screenName));
    }

    public function getLogs(ScreenInterface|string $nameOrScreen): ?string
    {
        $screen = $this->resolveScreen($nameOrScreen);
        $screenName = $this->generateScreenName($screen);

        $logFile = $this->getLogFile($screenName);

        if (!$this->filesystem->exists($logFile)) {
            return null;
        }

        return file_get_contents($logFile) ?: null;
    }

    public function attach(ScreenInterface|string $nameOrScreen): void
    {
        $screen = $this->resolveScreen($nameOrScreen);
        $screenName = $this->generateScreenName($screen);

        $process = new Process(['screen', '-r', $screenName]);

        $process->setTty(true);

        $process->run();
    }
}
