<?php

namespace SoureCode\Bundle\Screen\Entity;

interface ScreenInterface
{
    public function getName(): string;

    /**
     * @return list<string>
     */
    public function getCommand(): array;
}