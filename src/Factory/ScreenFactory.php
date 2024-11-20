<?php

namespace SoureCode\Bundle\Screen\Factory;

use SoureCode\Bundle\Screen\Model\ScreenInterface;

readonly class ScreenFactory implements ScreenFactoryInterface
{
    public function __construct(
        /**
         * @var class-string<ScreenInterface>
         */
        private string $screenClassName,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function create(string $name, array $config): ScreenInterface
    {
        return new $this->screenClassName($name, $config['command']);
    }
}