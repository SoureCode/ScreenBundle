<?php

namespace SoureCode\Bundle\Screen\Entity;

interface ScreenInterface
{
    public function getName(): string;

    /**
     * @return list<string>
     */
    public function getCommand(): array;

    /**
     * If the screen should be restarted on failure.
     */
    public function isRestartEnabled(): bool;
}