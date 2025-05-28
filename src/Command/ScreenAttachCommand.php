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
    name: 'screen:attach',
    description: 'Attach given screen sessions',
)]
class ScreenAttachCommand extends Command
{
    public function __construct(
        private readonly ScreenProviderInterface $screenProvider,
        private readonly ScreenManager $screenManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Name of screen sessions to attach');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if (!$name) {
            $io->error('Name of screen session is required');

            return Command::FAILURE;
        }

        if (!$this->screenProvider->has($name)) {
            $io->error(\sprintf("Screen session '%s' does not exist", $name));

            return Command::FAILURE;
        }

        $this->screenManager->attach($name);

        return Command::SUCCESS;
    }
}
