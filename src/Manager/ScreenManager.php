<?php

namespace SoureCode\Bundle\Screen\Manager;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\PhpProcess;
use Symfony\Component\Process\Process;

readonly class ScreenManager
{
    public function __construct(
        private string                  $baseDirectory,
        private string                  $environment,
        private bool                    $debug,
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

    private function getInfoLogFile(string $screenName): string
    {
        return Path::join($this->getLogDirectory(), sprintf("info-%s.log", $screenName));
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
        $infoLogFile = $this->getInfoLogFile($screenName);

        $phpBinary = $this->phpBinary();
        $consoleBinary = $this->consoleBinary();
        $user = get_current_user();

        $this->filesystem->dumpFile($logFile, '');
        $this->filesystem->dumpFile($infoLogFile, implode(PHP_EOL, [
            sprintf('Screen: %s', $screen->getName()),
            sprintf('Command: %s', implode(' ', $screen->getCommand())),
            sprintf('PHP: %s', $phpBinary),
            sprintf('Console: %s', $consoleBinary),
            sprintf('User: %s', $user),
            '',
        ]));

        $process = new Process([
            'screen',
            '-dmL',
            '-Logfile',
            $logFile,
            '-S',
            $screenName,
            $phpBinary,
            $consoleBinary,
            'screen:run',
            $screen->getName(),
        ], $this->baseDirectory,
            [
                'APP_ENV' => $this->environment,
                'APP_DEBUG' => $this->debug,
            ],
            null,
            5
        );

        $process->run(function ($type, $buffer) use ($infoLogFile) {
            echo $buffer; // @todo: remove later
            $this->filesystem->appendToFile($infoLogFile, $buffer);
        });

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

        // Attempt a graceful quit by sending ctrl+c
        $process = new Process(['screen', '-S', $screenName, '-X', 'stuff', "\003"]);

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

        // Attempt a graceful stop first
        if ($this->stop($screen)) {
            if ($this->isRunning($screen)) {
                $killProcess = new Process(['screen', '-S', $screenName, '-X', 'kill']);
                $killProcess->run();

                return $killProcess->isSuccessful();
            }

            return true;
        }

        return true;
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

    private function phpBinary(): string
    {
        $finder = new PhpExecutableFinder();
        $phpBinary = $finder->find(false);

        if ($phpBinary) {
            return $phpBinary;
        }

        throw new \RuntimeException('PHP binary not found.');
    }

    private function consoleBinary(): string
    {
        return Path::join($this->baseDirectory, 'bin', 'console');
    }
}