<?php

namespace SoureCode\Bundle\Screen\Command;

use Psr\EventDispatcher\EventDispatcherInterface;
use SoureCode\Bundle\Screen\Event\ScreenFailedEvent;
use SoureCode\Bundle\Screen\Event\ScreenSignalReceivedEvent;
use SoureCode\Bundle\Screen\Event\ScreenStartedEvent;
use SoureCode\Bundle\Screen\Event\ScreenStoppedEvent;
use SoureCode\Bundle\Screen\Entity\ScreenInterface;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'screen:run',
    description: 'Run the given screen.',
    hidden: true,
)]
class ScreenRunCommand extends Command implements SignalableCommandInterface
{
    private null|Process $process = null;
    private null|ScreenInterface $screen = null;

    public function __construct(
        private readonly ScreenProviderInterface  $screenProvider,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly string                   $baseDirectory,
        private readonly string                   $environment,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('screenName', InputArgument::REQUIRED, 'The screen name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$output instanceof ConsoleOutputInterface) {
            throw new \InvalidArgumentException('Output must be an instance of ConsoleOutputInterface.');
        }

        $errorOutput = $output->getErrorOutput();

        $screenName = $input->getArgument('screenName');

        if (!$this->screenProvider->has($screenName)) {
            throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $screenName));
        }

        $this->screen = $this->screenProvider->get($screenName);

        $command = $this->resolveCommand($this->screen);

        $this->process = new Process(
            $command,
            $this->baseDirectory,
            [
                'APP_ENV' => $this->environment,
            ],
            null,
            null
        );

        try {
            $this->process->start(function ($type, $buffer) use ($errorOutput, $output) {
                if (Process::ERR === $type) {
                    $errorOutput->write($buffer);
                } else {
                    $output->write($buffer);
                }
            });

            $this->eventDispatcher->dispatch(new ScreenStartedEvent($this->screen, $this->process));

            $this->process->wait();
        } catch (\Exception $exception) {
            $this->eventDispatcher->dispatch(new ScreenFailedEvent($this->screen, $this->process, $exception));

            $errorOutput->writeln($exception->getMessage());
        } finally {
            $this->eventDispatcher->dispatch(new ScreenStoppedEvent($this->screen, $this->process, $this->process->getExitCode()));
        }

        return $this->process->getExitCode(); // pass exit code
    }

    private function resolveCommand(ScreenInterface $screen): array
    {
        $command = $screen->getCommand();

        if (count($command) === 0) {
            throw new \InvalidArgumentException('The command must be an array with at least one element.');
        }

        $first = $screen->getCommand()[0];

        if ($first === 'php') {
            $command[0] = $this->phpBinary();
        }

        return $command;
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

    public function getSubscribedSignals(): array
    {
        return [
            \SIGINT,
            \SIGTERM,
            \SIGQUIT,
            \SIGABRT,
            // \SIGKILL, // This signal cannot be caught
            // \SIGSTOP, // This signal cannot be caught
            \SIGTSTP,
        ];
    }

    public function handleSignal(int $signal, false|int $previousExitCode = 0): int|false
    {
        $this->eventDispatcher->dispatch(new ScreenSignalReceivedEvent($this->screen, $this->process, $signal));

        if ($this->process?->isRunning()) {
            $this->process?->signal($signal);
        }

        return false;
    }
}
