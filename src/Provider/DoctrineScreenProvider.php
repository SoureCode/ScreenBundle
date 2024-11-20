<?php

namespace SoureCode\Bundle\Screen\Provider;

use Doctrine\ORM\EntityManagerInterface;
use SoureCode\Bundle\Screen\Model\ScreenInterface;

readonly class DoctrineScreenProvider implements ScreenProviderInterface
{
    private bool $mappingConfigured;

    public function __construct(
        private string                 $className,
        private EntityManagerInterface $entityManager,
    )
    {
        $this->mappingConfigured = $this->entityManager->getMetadataFactory()->hasMetadataFor($this->className);
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        if (!$this->mappingConfigured) {
            return [];
        }

        $repository = $this->entityManager->getRepository($this->className);
        $screens = $repository->findAll();

        return array_combine(array_map(static fn(ScreenInterface $screen) => $screen->getName(), $screens), $screens);
    }

    public function get(string $name): ScreenInterface
    {
        if (!$this->mappingConfigured) {
            throw new \RuntimeException('Mapping not configured.');
        }

        $repository = $this->entityManager->getRepository($this->className);

        $screen = $repository->findOneBy(['name' => $name]);

        if (null === $screen) {
            throw new \InvalidArgumentException(sprintf('Screen "%s" not found.', $name));
        }

        return $screen;
    }

    public function has(string $name): bool
    {
        if (!$this->mappingConfigured) {
            return false;
        }

        $repository = $this->entityManager->getRepository($this->className);

        return $repository->count(['name' => $name]) > 0;
    }
}