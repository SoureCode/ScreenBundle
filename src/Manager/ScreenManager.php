<?php

namespace SoureCode\Bundle\Screen\Manager;

use SoureCode\Bundle\Screen\Model\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

readonly class ScreenManager
{
    public function __construct(
        private string                  $baseDirectory,
        private Filesystem              $filesystem,
        private ScreenProviderInterface $provider,
    )
    {
    }

    private static function generateScreenName(ScreenInterface $screen): string
    {
        return hash('sha256', $screen->getName() . implode('', $screen->getCommand()));
    }

    private function getLogDirectory(): string
    {
        return Path::join($this->baseDirectory, 'var', 'log');
    }

    private function getLogFile(string $screenName): string
    {
        return Path::join($this->getLogDirectory(), sprintf("%s.log", $screenName));
    }

    public function start(ScreenInterface|string $nameOrScreen): bool
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if ($this->isRunning($nameOrScreen)) {
            return true;
        }

        $screenName = self::generateScreenName($screen);

        $logDir = $this->getLogDirectory();

        if (!$this->filesystem->exists($logDir)) {
            $this->filesystem->mkdir($logDir);
        }

        $logFile = $this->getLogFile($screenName);

        $this->filesystem->dumpFile($logFile, '');

        $command = $this->resolveCommand($screen);

        $process = new Process([
            'screen',
            '-dmL',
            '-Logfile',
            $logFile,
            '-S',
            $screenName,
            'bash',
            '-c',
            implode(' ', array_map('escapeshellarg', $command))
        ], $this->baseDirectory, null, null, 5);

        $process->run();

        return $process->isSuccessful();
    }

    public function stop(ScreenInterface|string $nameOrScreen): bool
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = self::generateScreenName($screen);

        $process = new Process(['screen', '-S', $screenName, '-X', 'quit']);

        $process->run();

        return $process->isSuccessful();
    }

    public function kill(ScreenInterface|string $nameOrScreen): bool
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        if (!$this->isRunning($screen)) {
            return true;
        }

        $screenName = self::generateScreenName($screen);

        $process = new Process(['screen', '-S', $screenName, '-X', 'kill']);

        $process->run();

        return $process->isSuccessful();
    }

    public function isRunning(ScreenInterface|string $nameOrScreen): bool
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = self::generateScreenName($screen);

        $process = new Process(['screen', '-ls']);

        $process->run();

        return str_contains($process->getOutput(), $screenName);
    }

    public function getLogs(ScreenInterface|string $nameOrScreen): ?string
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = self::generateScreenName($screen);

        $logFile = $this->getLogFile($screenName);

        if (!$this->filesystem->exists($logFile)) {
            return null;
        }

        return file_get_contents($logFile);
    }

    public function attach(ScreenInterface|string $nameOrScreen): void
    {
        if (is_string($nameOrScreen)) {
            if (!$this->provider->has($nameOrScreen)) {
                throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $nameOrScreen));
            }

            $screen = $this->provider->get($nameOrScreen);
        } else {
            $screen = $nameOrScreen;
        }

        $screenName = self::generateScreenName($screen);

        $process = new Process(['screen', '-r', $screenName]);

        $process->setTty(true);

        $process->run();
    }

    /**
     * @return string[]
     */
    private function resolveCommand(ScreenInterface $screen)
    {
        $command = $screen->getCommand();

        if (count($command) === 0) {
            throw new \InvalidArgumentException('The command must be an array with at least one element.');
        }

        $first = $screen->getCommand()[0];

        if ($first === 'php') {
            $finder = new PhpExecutableFinder();
            $phpBinary = $finder->find(false);

            if ($phpBinary) {
                $command[0] = $phpBinary;
            }
        }

        return $command;
    }
}