<?php

namespace SoureCode\Bundle\Screen\Provider;

use SoureCode\Bundle\Screen\Factory\ScreenFactoryInterface;
use SoureCode\Bundle\Screen\Model\ScreenInterface;

class ArrayScreenProvider implements ScreenProviderInterface
{
    /**
     * @var array<string, ScreenInterface>
     */
    private array $screens = [];

    public function __construct(
        private readonly ScreenFactoryInterface $screenFactory,
        /**
         * @var array<string, array{command: list<string>}>
         */
        private readonly array $screenDefinitions,
    ) {
    }

    public function all(): array
    {
        $keys = array_keys($this->screenDefinitions);

        foreach ($keys as $name) {
            if (!isset($this->screens[$name])) {
                $this->screens[$name] = $this->screenFactory->create($name, $this->screenDefinitions[$name]);
            }
        }

        return $this->screens;
    }

    public function get(string $name): ScreenInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(\sprintf('Screen "%s" not found.', $name));
        }

        if (!isset($this->screens[$name])) {
            $this->screens[$name] = $this->screenFactory->create($name, $this->screenDefinitions[$name]);
        }

        return $this->screens[$name];
    }

    public function has(string $name): bool
    {
        return isset($this->screenDefinitions[$name]);
    }
}
