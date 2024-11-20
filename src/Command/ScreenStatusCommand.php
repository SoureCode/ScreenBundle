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
    name: 'app:screen:status',
    description: 'Show status for all or given screen sessions',
)]
class ScreenStatusCommand extends Command
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
            ->addArgument('names', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Names of screen sessions');
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
                $isRunning = $this->screenManager->isRunning($screen);

                $io->writeln(sprintf("%s: %s", $screen->getName(), $isRunning ? 'running' : 'not running'));
            }

            return Command::SUCCESS;
        }

        foreach ($names as $name) {
            $isRunning = $this->screenManager->isRunning($name);

            $io->writeln(sprintf("%s: %s", $name, $isRunning ? 'running' : 'not running'));
        }

        return Command::SUCCESS;
    }
}
