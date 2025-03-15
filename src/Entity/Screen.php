<?php

namespace SoureCode\Bundle\Screen\Entity;

class Screen implements ScreenInterface
{
    public function __construct(
        protected ?string $name = null,
        /**
         * @var list<string>
         */
        protected ?array $command = null,
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @inheritDoc
     */
    public function getCommand(): array
    {
        return $this->command;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setCommand(array $command): void
    {
        $this->command = $command;
    }
}