<?php

namespace SoureCode\Bundle\Screen\Model;

class Screen implements ScreenInterface
{
    public function __construct(
        protected string $name,
        /**
         * @var list<string>
         */
        protected array $command,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<string>
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param list<string> $command
     */
    public function setCommand(array $command): void
    {
        $this->command = $command;
    }
}
