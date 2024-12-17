<?php

namespace SoureCode\Bundle\Screen\Factory;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;

interface ScreenFactoryInterface
{
    /**
     * @param array{command: list<string>} $config
     */
    public function create(string $name, array $config): ScreenInterface;
}