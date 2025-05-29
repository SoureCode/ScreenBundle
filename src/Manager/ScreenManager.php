<?php

namespace SoureCode\Bundle\Screen\Manager;

use Psr\Log\LoggerInterface;
use SoureCode\Bundle\Screen\Entity\ScreenInterface;
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

    public function generateScreenName(ScreenInterface $screen): string
    {
        return hash('sha256', $this->getBaseDirectory().$screen->getName().implode('', $screen->getCommand()));
    }

    private function getLogDirectory(): string
    {
        return Path::join($this->getBaseDirectory(), 'var', 'log');
    }

    private function getLogFile(string $screenName): string
    {
        return Path::join($this->getLogDirectory(), \sprintf('screen-%s.log', $screenName));
    }

    public function start(ScreenInterface|string $nameOrScreen): bool
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if ($this->isRunning($nameOrScreen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        $logDir = $this->getLogDirectory();

        if (!$this->filesystem->exists($logDir)) {
            $this->filesystem->mkdir($logDir);
        }

        $logFile = $this->getLogFile($screenName);

        $phpBinary = $this->phpBinary();
        $consoleBinary = $this->consoleBinary();

        $this->filesystem->remove($logFile);

        $this->logger->info(\sprintf('Starting screen "%s"', $screen->getName()), [
            'screen' => $screen->getName(),
            'command' => $screen->getCommand(),
            'php' => $phpBinary,
            'console' => $consoleBinary,
            'environment' => $this->environment,
        ]);

        $process = new Process([
            'screen',
            '-L',
            '-Logfile',
            $logFile,
            '-dmS',
            $screenName,
            $phpBinary,
            $consoleBinary,
            'screen:run',
            $screen->getName(),
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
            'php' => $phpBinary,
            'console' => $consoleBinary,
            'environment' => $this->environment,
            'output' => $process->getOutput(),
            'errorOutput' => $process->getErrorOutput(),
            'exitCode' => $process->getExitCode(),
        ]);

        return $process->isSuccessful();
    }

    public function stop(ScreenInterface|string $nameOrScreen): bool
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        // Attempt a graceful quit by sending ctrl+c
        $process = new Process(['screen', '-S', $screenName, '-X', 'stuff', "\003"]);

        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param int $timeout - Time to wait in seconds for the screen to kill after trying to gracefully stop it
     * @param int $sleep   - Time to sleep in microseconds between checks
     */
    public function gracefullyStop(ScreenInterface|string $nameOrScreen, int $timeout = 5, int $sleep = 1000): bool
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

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

    /**
     * @param int $timeout - Time to wait in seconds for the screen to kill after trying to gracefully stop it
     * @param int $sleep   - Time to sleep in microseconds between checks
     */
    public function kill(ScreenInterface|string $nameOrScreen, int $timeout = 5, int $sleep = 1000): bool
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = $this->generateScreenName($screen);

        $process = new Process(['screen', '-S', $screenName, '-X', 'kill']);
        $process->run();

        return $process->isSuccessful();
    }

    public function isRunning(ScreenInterface|string $nameOrScreen): bool
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = $this->generateScreenName($screen);

        $process = new Process(['screen', '-ls']);

        $process->run();

        return str_contains($process->getOutput(), $screenName);
    }

    public function getLogs(ScreenInterface|string $nameOrScreen): ?string
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = $this->generateScreenName($screen);

        $logFile = $this->getLogFile($screenName);

        if (!$this->filesystem->exists($logFile)) {
            return null;
        }

        return file_get_contents($logFile) ?: null;
    }

    public function attach(ScreenInterface|string $nameOrScreen): void
    {
        if (\is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = $this->generateScreenName($screen);

        $process = new Process(['screen', '-r', $screenName]);

        $process->setTty(true);

        $process->run();
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

    public function getBaseDirectory(): string
    {
        return $this->baseDirectory;
    }
}
