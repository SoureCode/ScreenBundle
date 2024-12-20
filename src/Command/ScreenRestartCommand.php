<?php

namespace SoureCode\Bundle\Screen\Command;

use SoureCode\Bundle\Screen\Manager\ScreenManager;
use SoureCode\Bundle\Screen\Provider\ScreenProviderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'screen:restart',
    description: 'Restarts all or given screen sessions. Starts them if they are not running.',
)]
class ScreenRestartCommand extends Command
{
    public function __construct(
        private readonly ScreenProviderInterface $screenProvider,
        private readonly ScreenManager $screenManager
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('names', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Names of screen sessions to restart')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $names = $input->getArgument('names');

        if (!empty($names)) {
            foreach ($names as $name) {
                if (!$this->screenProvider->has($name)) {
                    $io->error(sprintf("Screen session '%s' does not exist", $name));
                    return Command::FAILURE;
                }
            }
        } else {
            foreach ($this->screenProvider->all() as $screen) {
                if ($this->screenManager->gracefullyStop($screen)) {
                    $io->success(sprintf("Screen session '%s' stopped", $screen->getName()));
                } else {
                    $io->error(sprintf("Failed to stop screen session '%s'", $screen->getName()));
                }

                if ($this->screenManager->start($screen)) {
                    $io->success(sprintf("Screen session '%s' started", $screen->getName()));
                } else {
                    $io->error(sprintf("Failed to start screen session '%s'", $screen->getName()));
                }
            }

            return Command::SUCCESS;
        }

        foreach ($names as $name) {
            if ($this->screenManager->gracefullyStop($name)) {
                $io->success(sprintf("Screen session '%s' stopped", $name));
            } else {
                $io->error(sprintf("Failed to stop screen session '%s'", $name));
            }

            if ($this->screenManager->start($name)) {
                $io->success(sprintf("Screen session '%s' started", $name));
            } else {
                $io->error(sprintf("Failed to start screen session '%s'", $name));
            }
        }

        return Command::SUCCESS;
    }
}
