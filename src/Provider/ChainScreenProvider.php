<?php

namespace SoureCode\Bundle\Screen\Provider;

use SoureCode\Bundle\Screen\Model\ScreenInterface;

readonly class ChainScreenProvider implements ScreenProviderInterface
{
    public function __construct(
        /**
         * @var iterable<ScreenProviderInterface>
         */
        private iterable $providers,
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        $screens = [];

        foreach ($this->providers as $provider) {
            $screens = $provider->all();
        }

        $keys = [];

        foreach ($screens as $key => $screen) {
            if (in_array($key, $keys, true)) {
                throw new \InvalidArgumentException(sprintf('It is not allowed to have multiple screens with the same name "%s".', $key));
            }

            $keys[] = $key;
        }

        return array_merge(...$screens);
    }

    public function get(string $name): ScreenInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                return $provider->get($name);
            }
        }

        throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $name));
    }

    public function has(string $name): bool
    {
        $found = false;

        foreach ($this->providers as $provider) {
            if ($provider->has($name)) {
                $found = true;
                break;
            }
        }

        return $found;
    }
}