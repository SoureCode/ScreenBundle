<?php

namespace SoureCode\Bundle\Screen\Provider;

use SoureCode\Bundle\Screen\Entity\ScreenInterface;

interface ScreenProviderInterface
{
    /**
     * @return array<string, ScreenInterface>
     */
    public function all(): array;

    public function get(string $name): ScreenInterface;

    public function has(string $name): bool;
}
