<?php

namespace SoureCode\Bundle\Screen\Provider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use SoureCode\Bundle\Screen\Entity\ScreenInterface;

readonly class DoctrineScreenProvider implements ScreenProviderInterface
{
    private bool $mappingConfigured;

    public function __construct(
        private string                 $className,
        private EntityManagerInterface $entityManager,
    )
    {
        try {
            $metadataFactory = $this->entityManager->getMetadataFactory();

            $this->mappingConfigured = $metadataFactory->getMetadataFor($this->className) !== null;
        } catch (MappingException $exception) {
            if (str_contains($exception->getMessage(), $this->className)) {
                $this->mappingConfigured = false;
            } else {
                throw $exception;
            }
        }
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